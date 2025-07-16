<?php

namespace App\Console\Commands;

use App\Services\OfflineService;
use App\Models\OfflineOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOfflineOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'offline:sync {--user-id= : مزامنة طلبات مستخدم محدد} {--retry-failed : إعادة محاولة الطلبات الفاشلة}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'مزامنة الطلبات في وضع عدم الاتصال مع قاعدة البيانات';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('بدء مزامنة الطلبات في وضع عدم الاتصال...');

        // التحقق من حالة الاتصال
        if (!OfflineService::isOnline()) {
            $this->error('لا يوجد اتصال بقاعدة البيانات');
            return 1;
        }

        $userId = $this->option('user-id');
        $retryFailed = $this->option('retry-failed');

        try {
            if ($retryFailed) {
                // إعادة محاولة الطلبات الفاشلة
                $this->info('إعادة محاولة الطلبات الفاشلة...');
                $result = OfflineService::retryFailedOrders();
            } else {
                // مزامنة الطلبات المعلقة
                $this->info('مزامنة الطلبات المعلقة...');
                $result = OfflineService::syncOfflineOrders();
            }

            if ($result['success']) {
                $this->info($result['message']);
                
                if (isset($result['synced_count'])) {
                    $this->info("تم مزامنة {$result['synced_count']} طلب بنجاح");
                }
                
                if (isset($result['failed_count']) && $result['failed_count'] > 0) {
                    $this->warn("فشل {$result['failed_count']} طلب في المزامنة");
                    
                    if (isset($result['errors'])) {
                        foreach ($result['errors'] as $error) {
                            $this->error($error);
                        }
                    }
                }
            } else {
                $this->error($result['message']);
                return 1;
            }

            // عرض إحصائيات
            $this->displayStats($userId);

        } catch (\Exception $e) {
            $this->error('حدث خطأ أثناء المزامنة: ' . $e->getMessage());
            Log::error('خطأ في مزامنة الطلبات في وضع عدم الاتصال: ' . $e->getMessage());
            return 1;
        }

        $this->info('تم الانتهاء من المزامنة بنجاح!');
        return 0;
    }

    /**
     * عرض إحصائيات الطلبات
     */
    private function displayStats($userId = null)
    {
        $this->newLine();
        $this->info('=== إحصائيات الطلبات في وضع عدم الاتصال ===');

        $stats = OfflineService::getOfflineStats();
        
        $this->table(
            ['النوع', 'العدد'],
            [
                ['إجمالي الطلبات', $stats['stats']['total']],
                ['في انتظار المزامنة', $stats['stats']['pending']],
                ['تمت المزامنة', $stats['stats']['synced']],
                ['فشلت المزامنة', $stats['stats']['failed']],
                ['إجمالي المبلغ', $stats['stats']['total_amount'] . ' ريال'],
            ]
        );
    }
} 