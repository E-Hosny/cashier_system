<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\InvoiceNumberService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class UpdateExistingInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:update-existing {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing invoices with new invoice numbers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('بدء تحديث أرقام الفواتير الموجودة...');
        
        $orders = Order::whereNull('invoice_number')->orderBy('created_at')->get();
        
        if ($orders->isEmpty()) {
            $this->info('لا توجد فواتير تحتاج إلى تحديث.');
            return;
        }
        
        $this->info("تم العثور على {$orders->count()} فاتورة تحتاج إلى تحديث.");
        
        if ($this->option('dry-run')) {
            $this->info('وضع المحاكاة - لن يتم إجراء أي تغييرات');
        }
        
        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();
        
        $updatedCount = 0;
        
        foreach ($orders as $order) {
            try {
                // إنشاء رقم فاتورة بناءً على تاريخ إنشاء الطلب
                $orderDate = Carbon::parse($order->created_at);
                
                // الحصول على التسلسل الصحيح لهذا اليوم
                $sameDayOrders = Order::whereDate('created_at', $orderDate->format('Y-m-d'))
                    ->where('id', '<=', $order->id)
                    ->count();
                
                $sequence = $sameDayOrders;
                
                // إنشاء رقم الفاتورة المشفر
                $invoiceNumber = $this->generateInvoiceNumberForDate($sequence, $orderDate);
                
                if (!$this->option('dry-run')) {
                    $order->update(['invoice_number' => $invoiceNumber]);
                }
                
                $updatedCount++;
                
            } catch (\Exception $e) {
                $this->error("خطأ في تحديث الفاتورة {$order->id}: " . $e->getMessage());
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        if ($this->option('dry-run')) {
            $this->info("سيتم تحديث {$updatedCount} فاتورة.");
        } else {
            $this->info("تم تحديث {$updatedCount} فاتورة بنجاح.");
        }
    }
    
    /**
     * توليد رقم فاتورة لتاريخ محدد
     */
    private function generateInvoiceNumberForDate(int $sequence, Carbon $date): string
    {
        // تنسيق التاريخ: YYMMDD
        $dateCode = $date->format('ymd'); // مثال: 241219 لـ 19 ديسمبر 2024
        
        // تنسيق التسلسل: XXX (3 أرقام مع أصفار في البداية)
        $sequenceCode = str_pad($sequence, 3, '0', STR_PAD_LEFT);
        
        // دمج التاريخ مع التسلسل: YYMMDD-XXX
        $invoiceNumber = $dateCode . '-' . $sequenceCode;
        
        return $invoiceNumber;
    }
} 