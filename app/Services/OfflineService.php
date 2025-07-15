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
     * مزامنة الطلبات في وضع عدم الاتصال
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
        $pendingOrders = OfflineOrder::getPendingSync($userId);
        $syncedCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($pendingOrders as $offlineOrder) {
            try {
                DB::transaction(function () use ($offlineOrder) {
                    // تحويل الطلب إلى طلب عادي
                    $order = $offlineOrder->convertToOrder();
                    
                    // إنشاء عناصر الطلب
                    $offlineOrder->createOrderItems($order->id);
                    
                    // إنشاء حركات المخزون
                    $offlineOrder->createStockMovements($order->id);
                    
                    // تحديث المخزون
                    self::updateStockFromMovements($offlineOrder->stock_movements);
                    
                    // تحديث حالة المزامنة
                    $offlineOrder->updateSyncStatus('synced');
                });

                $syncedCount++;
                
            } catch (\Exception $e) {
                $error = 'خطأ في مزامنة الطلب ' . $offlineOrder->offline_id . ': ' . $e->getMessage();
                $errors[] = $error;
                Log::error($error);
                
                $offlineOrder->updateSyncStatus('failed', $e->getMessage());
                $failedCount++;
            }
        }

        return [
            'success' => true,
            'synced_count' => $syncedCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
            'message' => "تم مزامنة {$syncedCount} طلب بنجاح" . ($failedCount > 0 ? " وفشل {$failedCount} طلب" : "")
        ];
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