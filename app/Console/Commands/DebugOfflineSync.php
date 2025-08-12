<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OfflineOrder;
use App\Models\Order;
use App\Services\OfflineService;
use Illuminate\Support\Facades\Auth;

class DebugOfflineSync extends Command
{
    protected $signature = 'offline:debug {user_id?}';
    protected $description = 'تشخيص مشاكل مزامنة الطلبات الأوفلاين';

    public function handle()
    {
        $userId = $this->argument('user_id') ?: 1; // افتراضي للمستخدم الأول
        
        $this->info("🔍 تشخيص مزامنة الطلبات الأوفلاين للمستخدم: {$userId}");
        $this->newLine();
        
        // فحص الطلبات المعلقة
        $pendingOrders = OfflineOrder::whereIn('status', ['pending_sync', 'failed'])
            ->where('user_id', $userId)
            ->get();
        
        $this->info("📊 عدد الطلبات المعلقة: " . $pendingOrders->count());
        
        if ($pendingOrders->isEmpty()) {
            $this->info("✅ لا توجد طلبات معلقة للمزامنة");
            return 0;
        }
        
        $this->table(
            ['ID', 'Offline ID', 'حالة', 'رقم الفاتورة', 'المبلغ', 'تاريخ الإنشاء', 'آخر محاولة'],
            $pendingOrders->map(function($order) {
                return [
                    $order->id,
                    $order->offline_id,
                    $order->status,
                    $order->invoice_number,
                    $order->total,
                    $order->created_at,
                    $order->sync_attempted_at ?? 'لم يحاول'
                ];
            })
        );
        
        $this->newLine();
        
        // فحص الطلبات المزامنة
        $syncedOrders = Order::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();
        
        $this->info("📈 عدد الطلبات المزامنة اليوم: {$syncedOrders}");
        
        // فحص الأقفال
        $this->info("🔒 فحص الأقفال:");
        $syncLock = \Cache::has("sync_offline_orders_{$userId}");
        $quickLock = \Cache::has("sync_quick_lock_{$userId}");
        $invoiceLock = \Cache::has("invoice_numbering_system_lock");
        
        $this->line("   - قفل المزامنة العام: " . ($syncLock ? '🔒 مُقفل' : '🔓 مفتوح'));
        $this->line("   - قفل سريع: " . ($quickLock ? '🔒 مُقفل' : '🔓 مفتوح'));
        $this->line("   - قفل نظام الفواتير: " . ($invoiceLock ? '🔒 مُقفل' : '🔓 مفتوح'));
        
        $this->newLine();
        
        // اختبار المزامنة
        if ($this->confirm('هل تريد محاولة المزامنة الآن؟')) {
            $this->info("🔄 بدء المزامنة...");
            
            // تسجيل الدخول كالمستخدم المحدد
            Auth::loginUsingId($userId);
            
            $result = OfflineService::syncOfflineOrders();
            
            if ($result['success']) {
                $this->info("✅ نجحت المزامنة:");
                $this->line("   - طلبات مزامنة: " . ($result['synced_count'] ?? 0));
                $this->line("   - طلبات متخطاة: " . ($result['skipped_count'] ?? 0));
                $this->line("   - طلبات فاشلة: " . ($result['failed_count'] ?? 0));
                
                if (!empty($result['errors'])) {
                    $this->error("❌ الأخطاء:");
                    foreach ($result['errors'] as $error) {
                        $this->line("   - {$error}");
                    }
                }
            } else {
                $this->error("❌ فشلت المزامنة: " . $result['message']);
            }
        }
        
        // فحص الطلبات المتشابهة
        if ($this->confirm('هل تريد فحص الطلبات المتشابهة؟')) {
            $this->checkSimilarOrders($userId);
        }
        
        return 0;
    }
    
    private function checkSimilarOrders($userId)
    {
        $this->info("🔍 فحص الطلبات المتشابهة...");
        
        $orders = Order::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->with('items')
            ->get()
            ->groupBy('total');
        
        $duplicates = $orders->filter(function($group) {
            return $group->count() > 1;
        });
        
        if ($duplicates->isEmpty()) {
            $this->info("✅ لا توجد طلبات متشابهة");
            return;
        }
        
        $this->warn("⚠️  تم العثور على طلبات متشابهة:");
        
        foreach ($duplicates as $total => $orders) {
            $this->line("المبلغ: {$total} - عدد الطلبات: " . $orders->count());
            
            foreach ($orders as $order) {
                $itemsSignature = $order->items->map(function($item) {
                    return $item->product_name . ' x' . $item->quantity;
                })->implode(', ');
                
                $this->line("   - فاتورة: {$order->invoice_number} - العناصر: {$itemsSignature}");
            }
            $this->newLine();
        }
    }
} 