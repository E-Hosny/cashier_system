<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // مزامنة الطلبات في وضع عدم الاتصال كل دقيقة
        $schedule->command('offline:sync')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                \Log::error('فشل في مزامنة الطلبات في وضع عدم الاتصال');
            });

        // إعادة محاولة الطلبات الفاشلة كل 5 دقائق
        $schedule->command('offline:sync --retry-failed')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                \Log::error('فشل في إعادة محاولة الطلبات الفاشلة');
            });

        // تنظيف الطلبات المزامنة بنجاح كل ساعة
        $schedule->call(function () {
            \App\Services\OfflineService::cleanupSyncedOrders();
        })->hourly()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 