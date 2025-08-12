<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OfflineOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class CleanupOfflineOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'offline:cleanup {--dry-run : معاينة بدون تطبيق} {--force : إجبار التنظيف بدون تأكيد} {--check-duplicates : فحص الطلبات المكررة فقط}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تنظيف الطلبات الأوفلاين المعلقة والمكررة مع حماية شاملة من التكرار';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $checkDuplicatesOnly = $this->option('check-duplicates');
        
        $this->info("🔧 تنظيف الطلبات الأوفلاين المعلقة والمكررة");
        $this->newLine();
        
        if ($checkDuplicatesOnly) {
            // فحص الطلبات المكررة فقط
            $this->comprehensiveDuplicateCheck($dryRun, $force);
            return 0;
        }
        
        // 1. فحص شامل للطلبات المكررة
        $this->comprehensiveDuplicateCheck($dryRun, $force);
        
        // 2. البحث عن الطلبات المعلقة في حالة "syncing" لفترة طويلة
        $this->checkStuckSyncingOrders($dryRun, $force);
        
        // 3. البحث عن الطلبات الأوفلاين المزامنة مسبقاً
        $this->checkAlreadySyncedOrders($dryRun, $force);
        
        // 4. البحث عن العناصر المكررة
        $this->checkDuplicateItems($dryRun, $force);
        
        // 5. فحص حركات المخزون المكررة
        $this->checkDuplicateStockMovements($dryRun, $force);
        
        return 0;
    }
    
    /**
     * فحص شامل للطلبات المكررة
     */
    private function comprehensiveDuplicateCheck($dryRun, $force)
    {
        $this->info("🔍 فحص شامل للطلبات المكررة:");
        
        // 1. البحث عن طلبات مكررة بنفس رقم الفاتورة
        $this->info("   • فحص الطلبات المكررة بنفس رقم الفاتورة...");
        $duplicatesByInvoice = DB::select("
            SELECT invoice_number, COUNT(*) as count, GROUP_CONCAT(id) as order_ids
            FROM orders 
            WHERE invoice_number IS NOT NULL 
            GROUP BY invoice_number 
            HAVING COUNT(*) > 1
        ");
        
        if (count($duplicatesByInvoice) > 0) {
            $this->warn("     ⚠️  تم العثور على " . count($duplicatesByInvoice) . " مجموعة طلبات مكررة بنفس رقم الفاتورة");
            
            foreach ($duplicatesByInvoice as $duplicate) {
                $this->line("       - رقم الفاتورة: {$duplicate->invoice_number} ({$duplicate->count} طلبات)");
                
                if (!$dryRun && ($force || $this->confirm("هل تريد حذف الطلبات المكررة لرقم الفاتورة {$duplicate->invoice_number}؟"))) {
                    // احتفظ بالطلب الأقدم واحذف الباقي
                    $orderIds = explode(',', $duplicate->order_ids);
                    $keepOrderId = min($orderIds);
                    $deleteOrderIds = array_diff($orderIds, [$keepOrderId]);
                    
                    foreach ($deleteOrderIds as $orderId) {
                        $this->deleteDuplicateOrder($orderId);
                    }
                    
                    $this->info("       ✅ تم حذف " . count($deleteOrderIds) . " طلب مكرر");
                }
            }
        } else {
            $this->info("     ✅ لا توجد طلبات مكررة بنفس رقم الفاتورة");
        }
        
        // 2. البحث عن طلبات مكررة بنفس التوقيت والمبلغ والمستخدم
        $this->info("   • فحص الطلبات المكررة بنفس التوقيت والمبلغ...");
        $duplicatesByTimeAmount = DB::select("
            SELECT user_id, total, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as time_group, COUNT(*) as count, GROUP_CONCAT(id) as order_ids
            FROM orders 
            GROUP BY user_id, total, time_group
            HAVING COUNT(*) > 1 AND total > 0
        ");
        
        if (count($duplicatesByTimeAmount) > 0) {
            $this->warn("     ⚠️  تم العثور على " . count($duplicatesByTimeAmount) . " مجموعة طلبات محتملة التكرار");
            
            foreach ($duplicatesByTimeAmount as $duplicate) {
                $this->line("       - مستخدم: {$duplicate->user_id}, مبلغ: {$duplicate->total}, وقت: {$duplicate->time_group} ({$duplicate->count} طلبات)");
                
                // التحقق من تشابه عناصر الطلبات
                $orderIds = explode(',', $duplicate->order_ids);
                $similarOrders = $this->checkOrderItemsSimilarity($orderIds);
                
                if ($similarOrders && !$dryRun && ($force || $this->confirm("هل تريد حذف الطلبات المتشابهة؟"))) {
                    $keepOrderId = min($orderIds);
                    $deleteOrderIds = array_diff($orderIds, [$keepOrderId]);
                    
                    foreach ($deleteOrderIds as $orderId) {
                        $this->deleteDuplicateOrder($orderId);
                    }
                    
                    $this->info("       ✅ تم حذف " . count($deleteOrderIds) . " طلب مكرر");
                }
            }
        } else {
            $this->info("     ✅ لا توجد طلبات مكررة بنفس التوقيت والمبلغ");
        }
        
        // 3. فحص طلبات أوفلاين مكررة
        $this->info("   • فحص الطلبات الأوفلاين المكررة...");
        $duplicateOfflineOrders = DB::select("
            SELECT offline_id, COUNT(*) as count, GROUP_CONCAT(id) as offline_order_ids
            FROM offline_orders 
            WHERE offline_id IS NOT NULL 
            GROUP BY offline_id 
            HAVING COUNT(*) > 1
        ");
        
        if (count($duplicateOfflineOrders) > 0) {
            $this->warn("     ⚠️  تم العثور على " . count($duplicateOfflineOrders) . " طلب أوفلاين مكرر");
            
            foreach ($duplicateOfflineOrders as $duplicate) {
                $this->line("       - معرف أوفلاين: {$duplicate->offline_id} ({$duplicate->count} طلبات)");
                
                if (!$dryRun && ($force || $this->confirm("هل تريد حذف الطلبات الأوفلاين المكررة؟"))) {
                    $orderIds = explode(',', $duplicate->offline_order_ids);
                    $keepOrderId = max($orderIds); // احتفظ بالأحدث
                    $deleteOrderIds = array_diff($orderIds, [$keepOrderId]);
                    
                    OfflineOrder::whereIn('id', $deleteOrderIds)->delete();
                    $this->info("       ✅ تم حذف " . count($deleteOrderIds) . " طلب أوفلاين مكرر");
                }
            }
        } else {
            $this->info("     ✅ لا توجد طلبات أوفلاين مكررة");
        }
    }
    
    /**
     * التحقق من تشابه عناصر الطلبات
     */
    private function checkOrderItemsSimilarity($orderIds)
    {
        if (count($orderIds) < 2) return false;
        
        $firstOrderItems = OrderItem::where('order_id', $orderIds[0])
            ->orderBy('product_id')
            ->get(['product_id', 'quantity', 'price'])
            ->toArray();
        
        for ($i = 1; $i < count($orderIds); $i++) {
            $currentOrderItems = OrderItem::where('order_id', $orderIds[$i])
                ->orderBy('product_id')
                ->get(['product_id', 'quantity', 'price'])
                ->toArray();
            
            if ($firstOrderItems !== $currentOrderItems) {
                return false; // الطلبات مختلفة
            }
        }
        
        return true; // جميع الطلبات متشابهة
    }
    
    /**
     * حذف طلب مكرر مع جميع بياناته المرتبطة
     */
    private function deleteDuplicateOrder($orderId)
    {
        DB::transaction(function () use ($orderId) {
            // حذف عناصر الطلب
            OrderItem::where('order_id', $orderId)->delete();
            
            // حذف حركات المخزون
            StockMovement::where('related_order_id', $orderId)->delete();
            
            // حذف الطلب
            Order::where('id', $orderId)->delete();
        });
    }
    
    /**
     * فحص الطلبات المعلقة في حالة المزامنة
     */
    private function checkStuckSyncingOrders($dryRun, $force)
    {
        $this->info("🔄 فحص الطلبات المعلقة في حالة المزامنة:");
        
        // البحث عن الطلبات في حالة "syncing" لأكثر من 30 دقيقة
        $stuckOrders = OfflineOrder::where('status', 'syncing')
            ->where('sync_attempted_at', '<', now()->subMinutes(30))
            ->get();
        
        if ($stuckOrders->count() > 0) {
            $this->warn("     ⚠️  تم العثور على {$stuckOrders->count()} طلب معلق في حالة المزامنة");
            
            foreach ($stuckOrders as $order) {
                $this->line("       - {$order->offline_id} معلق منذ {$order->sync_attempted_at->diffForHumans()}");
                
                if (!$dryRun && ($force || $this->confirm("هل تريد إعادة تعيين الطلب إلى pending_sync؟"))) {
                    $order->updateSyncStatus('pending_sync');
                    $this->info("       ✅ تم إعادة تعيين الطلب {$order->offline_id}");
                }
            }
        } else {
            $this->info("     ✅ لا توجد طلبات معلقة في حالة المزامنة");
        }
    }
    
    /**
     * فحص الطلبات الأوفلاين المزامنة مسبقاً
     */
    private function checkAlreadySyncedOrders($dryRun, $force)
    {
        $this->info("✅ فحص الطلبات الأوفلاين المزامنة مسبقاً:");
        
        $alreadySynced = OfflineOrder::whereIn('status', ['pending_sync', 'failed'])
            ->whereHas('user', function($query) {
                // التحقق من وجود طلب عادي بنفس رقم الفاتورة
                $query->whereExists(function($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('orders')
                        ->whereRaw('orders.invoice_number = offline_orders.invoice_number');
                });
            })
            ->get();
        
        if ($alreadySynced->count() > 0) {
            $this->warn("     ⚠️  تم العثور على {$alreadySynced->count()} طلب أوفلاين مزامن مسبقاً");
            
            foreach ($alreadySynced as $order) {
                $this->line("       - {$order->offline_id} - فاتورة: {$order->invoice_number}");
                
                if (!$dryRun && ($force || $this->confirm("هل تريد تحديث حالة الطلب إلى synced؟"))) {
                    $order->updateSyncStatus('synced');
                    $this->info("       ✅ تم تحديث حالة الطلب {$order->offline_id}");
                }
            }
        } else {
            $this->info("     ✅ جميع الطلبات الأوفلاين في حالة صحيحة");
        }
    }
    
    /**
     * فحص العناصر المكررة
     */
    private function checkDuplicateItems($dryRun, $force)
    {
        $this->info("📦 فحص العناصر المكررة:");
        
        $duplicateItems = DB::select("
            SELECT order_id, product_id, size, COUNT(*) as count, GROUP_CONCAT(id) as item_ids
            FROM order_items 
            GROUP BY order_id, product_id, size
            HAVING COUNT(*) > 1
        ");
        
        if (count($duplicateItems) > 0) {
            $this->warn("     ⚠️  تم العثور على " . count($duplicateItems) . " مجموعة عناصر مكررة");
            
            foreach ($duplicateItems as $duplicate) {
                $this->line("       - طلب: {$duplicate->order_id}, منتج: {$duplicate->product_id} ({$duplicate->count} عناصر)");
                
                if (!$dryRun && ($force || $this->confirm("هل تريد دمج العناصر المكررة؟"))) {
                    $this->mergeDuplicateItems($duplicate);
                    $this->info("       ✅ تم دمج العناصر المكررة");
                }
            }
        } else {
            $this->info("     ✅ لا توجد عناصر مكررة");
        }
    }
    
    /**
     * فحص حركات المخزون المكررة
     */
    private function checkDuplicateStockMovements($dryRun, $force)
    {
        $this->info("📊 فحص حركات المخزون المكررة:");
        
        $duplicateMovements = DB::select("
            SELECT related_order_id, product_id, type, COUNT(*) as count, GROUP_CONCAT(id) as movement_ids
            FROM stock_movements 
            WHERE related_order_id IS NOT NULL
            GROUP BY related_order_id, product_id, type
            HAVING COUNT(*) > 1
        ");
        
        if (count($duplicateMovements) > 0) {
            $this->warn("     ⚠️  تم العثور على " . count($duplicateMovements) . " مجموعة حركات مخزون مكررة");
            
            foreach ($duplicateMovements as $duplicate) {
                $this->line("       - طلب: {$duplicate->related_order_id}, منتج: {$duplicate->product_id} ({$duplicate->count} حركات)");
                
                if (!$dryRun && ($force || $this->confirm("هل تريد دمج حركات المخزون المكررة؟"))) {
                    $this->mergeDuplicateStockMovements($duplicate);
                    $this->info("       ✅ تم دمج حركات المخزون المكررة");
                }
            }
        } else {
            $this->info("     ✅ لا توجد حركات مخزون مكررة");
        }
    }
    
    /**
     * دمج العناصر المكررة
     */
    private function mergeDuplicateItems($duplicate)
    {
        $itemIds = explode(',', $duplicate->item_ids);
        $items = OrderItem::whereIn('id', $itemIds)->get();
        
        $totalQuantity = $items->sum('quantity');
        $averagePrice = $items->avg('price');
        
        // احتفظ بأول عنصر وحدث الكمية
        $keepItem = $items->first();
        $keepItem->update([
            'quantity' => $totalQuantity,
            'price' => $averagePrice
        ]);
        
        // احذف باقي العناصر
        OrderItem::whereIn('id', array_slice($itemIds, 1))->delete();
    }
    
    /**
     * دمج حركات المخزون المكررة
     */
    private function mergeDuplicateStockMovements($duplicate)
    {
        $movementIds = explode(',', $duplicate->movement_ids);
        $movements = StockMovement::whereIn('id', $movementIds)->get();
        
        $totalQuantity = $movements->sum('quantity');
        
        // احتفظ بأول حركة وحدث الكمية
        $keepMovement = $movements->first();
        $keepMovement->update(['quantity' => $totalQuantity]);
        
        // احذف باقي الحركات
        StockMovement::whereIn('id', array_slice($movementIds, 1))->delete();
    }
} 