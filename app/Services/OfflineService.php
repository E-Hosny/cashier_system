<?php

namespace App\Services;

use App\Models\OfflineOrder;
use App\Models\OfflineCache;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\CashierShift;
use App\Services\InvoiceNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema; // Added for Schema facade

class OfflineService
{
    /**
     * التحقق من حالة الاتصال
     */
    public static function isOnline()
    {
        try {
            // محاولة الاتصال بقاعدة البيانات
            DB::connection()->getPdo();
            
            // محاولة الاتصال بخادم خارجي للتحقق من الإنترنت
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://www.google.com');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $result !== false && $httpCode >= 200 && $httpCode < 400;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * إنشاء طلب في وضع عدم الاتصال
     */
    public static function createOfflineOrder($data)
    {
        try {
            // إنشاء معرف فريد للطلب
            $offlineId = OfflineOrder::generateOfflineId();
            
            // إنشاء رقم الفاتورة
            $invoiceNumber = InvoiceNumberService::generateInvoiceNumber();
            
            // حساب حركات المخزون
            $stockMovements = self::calculateStockMovements($data['items']);
            
            // إنشاء الطلب في وضع عدم الاتصال
            $offlineOrder = OfflineOrder::create([
                'offline_id' => $offlineId,
                'user_id' => Auth::id(),
                'cashier_shift_id' => CashierShift::getActiveShift(Auth::id())?->id,
                'total' => $data['total_price'],
                'payment_method' => $data['payment_method'],
                'status' => 'pending_sync',
                'invoice_number' => $invoiceNumber,
                'items' => $data['items'],
                'stock_movements' => $stockMovements,
            ]);

            // تخزين البيانات في التخزين المؤقت المحلي
            self::cacheOrderData($offlineOrder);

            return [
                'success' => true,
                'offline_id' => $offlineId,
                'invoice_number' => $invoiceNumber,
                'message' => 'تم إنشاء الطلب في وضع عدم الاتصال بنجاح!'
            ];

        } catch (\Exception $e) {
            Log::error('خطأ في إنشاء طلب في وضع عدم الاتصال: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'حدث خطأ في إنشاء الطلب: ' . $e->getMessage()
            ];
        }
    }

    /**
     * حساب حركات المخزون للطلبات
     */
    private static function calculateStockMovements($items)
    {
        $stockMovements = [];
        
        // تجميع جميع المنتجات المطلوبة
        $productIds = collect($items)->pluck('product_id')->unique();
        $products = Product::select('id', 'type', 'stock')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
        
        // تجميع جميع المكونات المطلوبة
        $finishedProductIds = $products->where('type', 'finished')->keys();
        $ingredients = collect();
        if ($finishedProductIds->isNotEmpty()) {
            $ingredients = DB::table('ingredients')
                ->select('finished_product_id', 'raw_material_id', 'quantity_consumed', 'size')
                ->whereIn('finished_product_id', $finishedProductIds)
                ->get()
                ->groupBy('finished_product_id');
        }
        
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (!$product) continue;

            // إذا كان منتج نهائي، ابحث عن المكونات للمقاس المحدد
            if ($product->type === 'finished') {
                $productIngredients = $ingredients->get($product->id, collect());
                $ingredientsForSize = $productIngredients->where('size', $item['size']);

                foreach ($ingredientsForSize as $ingredient) {
                    $quantityToDeduct = $item['quantity'] * $ingredient->quantity_consumed;
                    
                    $stockMovements[] = [
                        'product_id' => $ingredient->raw_material_id,
                        'quantity' => -$quantityToDeduct,
                        'type' => 'sale_deduction',
                    ];
                }
            } 
            // إذا كان منتج بسيط (مادة خام تباع مباشرة)
            else if ($product->type === 'raw' && $product->stock !== null) {
                $stockMovements[] = [
                    'product_id' => $product->id,
                    'quantity' => -$item['quantity'],
                    'type' => 'sale_deduction',
                ];
            }
        }
        
        return $stockMovements;
    }

    /**
     * تخزين بيانات الطلب في التخزين المؤقت المحلي
     */
    private static function cacheOrderData($offlineOrder)
    {
        $userId = Auth::id();
        
        // تخزين بيانات الطلب
        OfflineCache::set($userId, 'offline_order_' . $offlineOrder->offline_id, [
            'order' => $offlineOrder->toArray(),
            'timestamp' => now()->toISOString(),
        ]);
        
        // تحديث قائمة الطلبات المعلقة
        $pendingOrders = OfflineOrder::getPendingSync($userId);
        OfflineCache::set($userId, 'pending_orders', $pendingOrders->toArray());
    }

    /**
     * مزامنة الطلبات في وضع عدم الاتصال - محسنة ومحمية من التضارب والتكرار
     */
    public static function syncOfflineOrders()
    {
        if (!self::isOnline()) {
            return [
                'success' => false,
                'message' => 'لا يوجد اتصال بالإنترنت'
            ];
        }

        $userId = Auth::id();
        
        // قفل شامل لمزامنة المستخدم الواحد - حماية مشددة
        $globalSyncLockKey = "global_sync_user_{$userId}";
        if (\Illuminate\Support\Facades\Cache::has($globalSyncLockKey)) {
            Log::info("تم رفض طلب مزامنة للمستخدم {$userId} - مزامنة شاملة جارية بالفعل");
            return [
                'success' => false,
                'message' => 'عملية مزامنة شاملة جارية بالفعل، يرجى الانتظار'
            ];
        }
        
        // التحقق من وجود مزامنة جارية - حماية مشددة
        $syncLockKey = "sync_offline_orders_{$userId}";
        if (\Illuminate\Support\Facades\Cache::has($syncLockKey)) {
            Log::info("تم رفض طلب مزامنة للمستخدم {$userId} - عملية مزامنة جارية بالفعل");
            return [
                'success' => false,
                'message' => 'عملية مزامنة جارية بالفعل، يرجى الانتظار'
            ];
        }
        
        // قفل إضافي لفترة قصيرة لمنع الطلبات المتتالية السريعة
        $quickLockKey = "sync_quick_lock_{$userId}";
        if (\Illuminate\Support\Facades\Cache::has($quickLockKey)) {
            Log::info("تم رفض طلب مزامنة للمستخدم {$userId} - طلبات متتالية سريعة");
            return [
                'success' => false,
                'message' => 'طلبات مزامنة سريعة جداً، يرجى الانتظار'
            ];
        }
        
        // وضع قفل سريع لمدة 5 ثوانٍ
        \Illuminate\Support\Facades\Cache::put($quickLockKey, true, 5);
        
        // قفل شامل لنظام ترقيم الفواتير أثناء المزامنة
        $invoiceSystemLockKey = "invoice_numbering_system_lock";
        $invoiceSystemLocked = false;
        
        try {
            // قفل شامل لمزامنة المستخدم لمدة 15 دقيقة
            \Illuminate\Support\Facades\Cache::put($globalSyncLockKey, true, 900);
            
            // قفل عملية المزامنة لمدة 10 دقائق
            \Illuminate\Support\Facades\Cache::put($syncLockKey, true, 600);
            
            $pendingOrders = OfflineOrder::getPendingSync($userId);
            
            Log::info("🔄 بدء مزامنة للمستخدم {$userId} - عدد الطلبات المعلقة: " . $pendingOrders->count());
            
            if ($pendingOrders->isEmpty()) {
                return [
                    'success' => true,
                    'synced_count' => 0,
                    'failed_count' => 0,
                    'skipped_count' => 0,
                    'message' => 'لا توجد طلبات معلقة للمزامنة'
                ];
            }
            
            // قفل نظام ترقيم الفواتير لمنع التضارب مع الطلبات الجديدة
            if (!self::lockInvoiceNumberingSystem($invoiceSystemLockKey)) {
                return [
                    'success' => false,
                    'message' => 'نظام الفواتير مشغول، يرجى المحاولة لاحقاً'
                ];
            }
            $invoiceSystemLocked = true;
            
            $syncedCount = 0;
            $failedCount = 0;
            $errors = [];
            $skippedCount = 0;
            $renumberedCount = 0;
            
            // إنشاء قاموس للفواتير المزامنة في هذه الدورة لمنع التكرار داخل الدورة الواحدة
            $syncedInvoiceNumbers = [];

            foreach ($pendingOrders as $offlineOrder) {
                try {
                    Log::info("🔍 فحص الطلب {$offlineOrder->offline_id} - الحالة: {$offlineOrder->status} - الفاتورة: {$offlineOrder->invoice_number}");
                    
                    // طبقة الحماية الأولى: التحقق من الحالة مرة أخرى
                    $offlineOrder->refresh();
                    
                    if ($offlineOrder->status !== 'pending_sync' && $offlineOrder->status !== 'failed') {
                        Log::info("⏸️ تم تخطي الطلب {$offlineOrder->offline_id} - الحالة: {$offlineOrder->status}");
                        $skippedCount++;
                        continue;
                    }
                    
                    // طبقة الحماية الثانية: التحقق من المزامنة المسبقة بواسطة رقم الفاتورة
                    $existingOrder = Order::where('invoice_number', $offlineOrder->invoice_number)->first();
                    if ($existingOrder) {
                        Log::warning("⏸️ الطلب {$offlineOrder->offline_id} مزامن مسبقاً في قاعدة البيانات - رقم الفاتورة: {$offlineOrder->invoice_number}");
                        $offlineOrder->updateSyncStatus('synced');
                        $skippedCount++;
                        continue;
                    }
                    
                    // طبقة الحماية الثالثة: التحقق من المزامنة في نفس الدورة
                    if (in_array($offlineOrder->invoice_number, $syncedInvoiceNumbers)) {
                        Log::warning("⏸️ الطلب {$offlineOrder->offline_id} مزامن بالفعل في هذه الدورة - رقم الفاتورة: {$offlineOrder->invoice_number}");
                        $offlineOrder->updateSyncStatus('synced');
                        $skippedCount++;
                        continue;
                    }
                    
                    // طبقة الحماية الرابعة: التحقق من تكرار offline_id (إذا كان metadata موجود)
                    try {
                        $existingByOfflineId = Order::where('user_id', $userId)
                            ->whereJsonContains('metadata->offline_id', $offlineOrder->offline_id)
                            ->first();
                        
                        if ($existingByOfflineId) {
                            Log::warning("الطلب {$offlineOrder->offline_id} مزامن مسبقاً بنفس offline_id");
                            $offlineOrder->updateSyncStatus('synced');
                            $skippedCount++;
                            continue;
                        }
                    } catch (\Exception $e) {
                        // تجاهل الخطأ إذا كان العمود metadata غير موجود
                        Log::info("تخطي فحص metadata للطلب {$offlineOrder->offline_id} - العمود غير متوفر");
                    }
                    
                    // طبقة الحماية الخامسة: التحقق من تشابه المحتوى والتوقيت (فقط للطلبات المتطابقة تماماً)
                    $timeThreshold = $offlineOrder->created_at->subSeconds(30);
                    $timeThresholdEnd = $offlineOrder->created_at->addSeconds(30);
                    
                    $itemsCount = count($offlineOrder->items);
                    $itemsSignature = collect($offlineOrder->items)->map(function($item) {
                        return $item['product_name'] . '_' . $item['quantity'] . '_' . $item['price'];
                    })->sort()->implode('|');
                    
                    $similarOrder = Order::where('user_id', $userId)
                        ->where('total', $offlineOrder->total)
                        ->whereBetween('created_at', [$timeThreshold, $timeThresholdEnd])
                        ->with('items')
                        ->get()
                        ->filter(function($order) use ($itemsSignature, $itemsCount) {
                            if ($order->items->count() !== $itemsCount) {
                                return false;
                            }
                            $orderSignature = $order->items->map(function($item) {
                                return $item->product_name . '_' . $item['quantity'] . '_' . $item['price'];
                            })->sort()->implode('|');
                            return $orderSignature === $itemsSignature;
                        })
                        ->first();
                    
                    if ($similarOrder) {
                        Log::warning("الطلب {$offlineOrder->offline_id} مطابق تماماً لطلب موجود (ID: {$similarOrder->id}) - المبلغ: {$offlineOrder->total}");
                        $offlineOrder->updateSyncStatus('synced');
                        $skippedCount++;
                        continue;
                    }
                    
                    // طبقة الحماية السادسة: قفل على مستوى الطلب الواحد
                    $orderLockKey = "sync_order_{$offlineOrder->offline_id}";
                    if (\Illuminate\Support\Facades\Cache::has($orderLockKey)) {
                        Log::info("الطلب {$offlineOrder->offline_id} قيد المزامنة في عملية أخرى");
                        $skippedCount++;
                        continue;
                    }
                    
                    // وضع قفل على الطلب لمدة 5 دقائق
                    \Illuminate\Support\Facades\Cache::put($orderLockKey, true, 300);
                    
                    try {
                        // تحديث حالة الطلب إلى "قيد المزامنة"
                        Log::info("🔄 بدء مزامنة الطلب {$offlineOrder->offline_id} - الفاتورة: {$offlineOrder->invoice_number}");
                        $offlineOrder->updateSyncStatus('syncing');
                        
                        // التحقق مرة أخيرة قبل بدء المعاملة
                        $doubleCheckOrder = Order::where('invoice_number', $offlineOrder->invoice_number)->first();
                        if ($doubleCheckOrder) {
                            Log::warning("تم العثور على طلب مزامن بنفس رقم الفاتورة أثناء المزامنة: {$offlineOrder->invoice_number}");
                            $offlineOrder->updateSyncStatus('synced');
                            $skippedCount++;
                            continue;
                        }
                        
                        DB::transaction(function () use ($offlineOrder, &$renumberedCount, &$syncedInvoiceNumbers) {
                            // التحقق من تضارب رقم الفاتورة مع الأرقام المولدة حديثاً
                            $currentInvoiceNumber = $offlineOrder->invoice_number;
                            $needsRenumbering = self::checkInvoiceNumberConflict($currentInvoiceNumber);
                            
                            if ($needsRenumbering) {
                                // إعادة ترقيم الفاتورة لتجنب التضارب
                                $newInvoiceNumber = \App\Services\InvoiceNumberService::generateInvoiceNumber();
                                
                                Log::info("إعادة ترقيم الطلب {$offlineOrder->offline_id} من {$currentInvoiceNumber} إلى {$newInvoiceNumber}");
                                
                                // تحديث رقم الفاتورة في الطلب الأوفلاين
                                $offlineOrder->update(['invoice_number' => $newInvoiceNumber]);
                                $renumberedCount++;
                            }
                            
                            // 1. تحويل الطلب إلى طلب عادي مع metadata لتتبع المصدر
                            $order = self::convertOfflineOrderToOrder($offlineOrder);
                            
                            // 2. التحقق من نجاح إنشاء الطلب
                            if (!$order || !$order->id) {
                                throw new \Exception('فشل في إنشاء الطلب العادي');
                            }
                            
                            // إضافة رقم الفاتورة إلى القاموس لمنع التكرار في نفس الدورة
                            $syncedInvoiceNumbers[] = $order->invoice_number;
                            
                            // 3. إنشاء عناصر الطلب مع التحقق من عدم وجودها مسبقاً
                            $existingItems = OrderItem::where('order_id', $order->id)->count();
                            if ($existingItems === 0) {
                                $itemsCreated = $offlineOrder->createOrderItems($order->id);
                                if (!$itemsCreated) {
                                    throw new \Exception('فشل في إنشاء عناصر الطلب');
                                }
                            } else {
                                Log::warning("عناصر الطلب موجودة مسبقاً للطلب {$order->id}");
                            }
                            
                            // 4. إنشاء حركات المخزون مع التحقق من عدم وجودها مسبقاً
                            $existingMovements = StockMovement::where('related_order_id', $order->id)->count();
                            if ($existingMovements === 0 && !empty($offlineOrder->stock_movements)) {
                                $movementsCreated = $offlineOrder->createStockMovements($order->id);
                                
                                // 5. تحديث المخزون
                                self::updateStockFromMovements($offlineOrder->stock_movements);
                            } else {
                                if ($existingMovements > 0) {
                                    Log::warning("حركات المخزون موجودة مسبقاً للطلب {$order->id}");
                                }
                            }
                            
                            // 6. تحديث حالة المزامنة إلى مكتملة
                            $offlineOrder->updateSyncStatus('synced');
                        });

                        $syncedCount++;
                        Log::info("✅ تم مزامنة الطلب {$offlineOrder->offline_id} بنجاح - رقم الفاتورة: {$offlineOrder->invoice_number} - المبلغ: {$offlineOrder->total}");
                        
                    } finally {
                        // إزالة قفل الطلب
                        \Illuminate\Support\Facades\Cache::forget($orderLockKey);
                    }
                    
                } catch (\Exception $e) {
                    $error = 'خطأ في مزامنة الطلب ' . $offlineOrder->offline_id . ': ' . $e->getMessage();
                    $errors[] = $error;
                    Log::error($error, [
                        'offline_order_id' => $offlineOrder->id,
                        'offline_id' => $offlineOrder->offline_id,
                        'invoice_number' => $offlineOrder->invoice_number,
                        'exception' => $e
                    ]);
                    
                    // إعادة تعيين الحالة إلى فاشلة
                    $offlineOrder->updateSyncStatus('failed', $e->getMessage());
                    $failedCount++;
                }
            }

            return [
                'success' => true,
                'synced_count' => $syncedCount,
                'failed_count' => $failedCount,
                'skipped_count' => $skippedCount,
                'renumbered_count' => $renumberedCount,
                'errors' => $errors,
                'message' => "تم مزامنة {$syncedCount} طلب بنجاح" . 
                           ($renumberedCount > 0 ? "، تم إعادة ترقيم {$renumberedCount} فاتورة" : "") .
                           ($skippedCount > 0 ? "، تم تخطي {$skippedCount} طلب" : "") .
                           ($failedCount > 0 ? "، فشل {$failedCount} طلب" : "")
            ];
            
        } finally {
            // إزالة الأقفال
            \Illuminate\Support\Facades\Cache::forget($syncLockKey);
            \Illuminate\Support\Facades\Cache::forget($globalSyncLockKey);
            
            if ($invoiceSystemLocked) {
                self::unlockInvoiceNumberingSystem($invoiceSystemLockKey);
            }
        }
    }
    
    /**
     * قفل نظام ترقيم الفواتير أثناء المزامنة
     */
    private static function lockInvoiceNumberingSystem($lockKey): bool
    {
        // محاولة الحصول على القفل لمدة 5 ثوان
        $attempts = 0;
        $maxAttempts = 5;
        
        while ($attempts < $maxAttempts) {
            if (!\Illuminate\Support\Facades\Cache::has($lockKey)) {
                // الحصول على القفل لمدة 15 دقيقة
                \Illuminate\Support\Facades\Cache::put($lockKey, Auth::id(), 900);
                return true;
            }
            
            sleep(1);
            $attempts++;
        }
        
        return false;
    }
    
    /**
     * إلغاء قفل نظام ترقيم الفواتير
     */
    private static function unlockInvoiceNumberingSystem($lockKey): void
    {
        \Illuminate\Support\Facades\Cache::forget($lockKey);
    }
    
    /**
     * التحقق من تضارب رقم الفاتورة
     */
    private static function checkInvoiceNumberConflict($invoiceNumber): bool
    {
        // استخراج التاريخ والرقم التسلسلي من رقم الفاتورة
        if (!preg_match('/^(\d{6})-(\d{3})$/', $invoiceNumber, $matches)) {
            return true; // رقم غير صحيح، يحتاج إعادة ترقيم
        }
        
        $dateCode = $matches[1];
        $sequenceNumber = (int)$matches[2];
        
        // الحصول على آخر رقم تسلسلي حالي من النظام
        $currentSequence = \App\Models\InvoiceSequence::where('date_code', $dateCode)->value('current_sequence') ?? 0;
        
        // إذا كان الرقم التسلسلي للطلب الأوفلاين أقل من أو يساوي الرقم الحالي
        // فهذا يعني أن هناك تضارب محتمل
        if ($sequenceNumber <= $currentSequence) {
            // التحقق من وجود طلب آخر بنفس الرقم
            $existsInOrders = Order::where('invoice_number', $invoiceNumber)->exists();
            
            if ($existsInOrders) {
                return true; // يحتاج إعادة ترقيم
            }
        }
        
        return false; // لا يوجد تضارب
    }

    /**
     * تحديث المخزون من حركات المخزون
     */
    private static function updateStockFromMovements($stockMovements)
    {
        if (empty($stockMovements)) {
            return;
        }

        foreach ($stockMovements as $movement) {
            DB::table('products')
                ->where('id', $movement['product_id'])
                ->increment('stock', $movement['quantity']);
        }
    }

    /**
     * تحويل الطلب الأوفلاين إلى طلب عادي مع metadata للتتبع
     */
    private static function convertOfflineOrderToOrder($offlineOrder)
    {
        $orderData = [
            'total' => $offlineOrder->total,
            'payment_method' => $offlineOrder->payment_method,
            'status' => 'completed',
            'cashier_shift_id' => $offlineOrder->cashier_shift_id,
            'invoice_number' => $offlineOrder->invoice_number,
            'tenant_id' => $offlineOrder->user->tenant_id,
            'user_id' => $offlineOrder->user_id,
            'created_at' => $offlineOrder->created_at, // الحفاظ على التاريخ الأصلي
            'updated_at' => now(),
        ];

        $order = Order::create($orderData);
        
        // إضافة metadata للتتبع (إذا كان الجدول يدعم ذلك)
        if (Schema::hasColumn('orders', 'metadata')) {
            $order->update([
                'metadata' => json_encode([
                    'source' => 'offline_sync',
                    'offline_id' => $offlineOrder->offline_id,
                    'synced_at' => now()->toISOString(),
                ])
            ]);
        }
        
        return $order;
    }

    /**
     * الحصول على إحصائيات الطلبات في وضع عدم الاتصال
     */
    public static function getOfflineStats()
    {
        $userId = Auth::id();
        
        return [
            'stats' => OfflineOrder::getStats($userId),
            'pending_orders' => OfflineOrder::getPendingSync($userId)->count(),
            'failed_orders' => OfflineOrder::getFailedSync($userId)->count(),
            'cache_size' => OfflineCache::getSize($userId),
        ];
    }

    /**
     * حذف الطلبات المزامنة بنجاح
     */
    public static function cleanupSyncedOrders()
    {
        $userId = Auth::id();
        
        // حذف الطلبات المزامنة بنجاح
        $deletedCount = OfflineOrder::where('user_id', $userId)
            ->where('status', 'synced')
            ->delete();
        
        // حذف البيانات المخزنة مؤقتاً للطلبات المحذوفة
        OfflineCache::clear($userId);
        
        return $deletedCount;
    }

    /**
     * إعادة محاولة مزامنة الطلبات الفاشلة
     */
    public static function retryFailedOrders()
    {
        $userId = Auth::id();
        $failedOrders = OfflineOrder::getFailedSync($userId);
        
        foreach ($failedOrders as $order) {
            $order->updateSyncStatus('pending_sync');
        }
        
        return self::syncOfflineOrders();
    }

    /**
     * تحميل البيانات المطلوبة للعمل في وضع عدم الاتصال
     */
    public static function loadOfflineData()
    {
        $userId = Auth::id();
        
        // تحميل المنتجات
        $products = Product::with('category')
            ->where('type', 'finished')
            ->latest()
            ->get()
            ->append('available_sizes');
        
        // تحميل الفئات
        $categories = \App\Models\Category::all();
        
        // تخزين البيانات مؤقتاً
        OfflineCache::set($userId, 'products', $products->toArray());
        OfflineCache::set($userId, 'categories', $categories->toArray());
        
        return [
            'products' => $products,
            'categories' => $categories,
        ];
    }

    /**
     * الحصول على البيانات المخزنة مؤقتاً
     */
    public static function getCachedData($key)
    {
        $userId = Auth::id();
        return OfflineCache::get($userId, $key);
    }

    /**
     * التحقق من وجود طلبات معلقة للمزامنة
     */
    public static function hasPendingOrders()
    {
        $userId = Auth::id();
        return OfflineOrder::where('user_id', $userId)
            ->where('status', 'pending_sync')
            ->exists();
    }
} 