<?php

namespace App\Console\Commands;

use App\Services\InvoiceNumberService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class TestInvoiceNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:test {--count=5 : Number of test invoices to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the invoice numbering system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int)$this->option('count');
        
        $this->info("توليد {$count} رقم فاتورة للاختبار...");
        $this->newLine();
        
        $this->table(
            ['الرقم التسلسلي', 'رقم الفاتورة', 'معلومات الفك التشفير'],
            $this->generateTestData($count)
        );
        
        $this->newLine();
        $this->info('اختبار فك التشفير:');
        
        // اختبار فك التشفير
        $testNumbers = $this->generateTestData(3);
        foreach ($testNumbers as $row) {
            $invoiceNumber = $row[1];
            $info = InvoiceNumberService::getInvoiceInfo($invoiceNumber);
            
            if ($info) {
                $this->info("رقم الفاتورة: {$invoiceNumber}");
                $this->info("  - التسلسل اليومي: {$info['day_number']}");
                $this->info("  - التاريخ: {$info['formatted_date']}");
                $this->info("  - هل هو اليوم الحالي: " . ($info['is_today'] ? 'نعم' : 'لا'));
                $this->newLine();
            }
        }
    }
    
    /**
     * توليد بيانات الاختبار
     */
    private function generateTestData(int $count): array
    {
        $data = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $invoiceNumber = InvoiceNumberService::generateInvoiceNumber();
            $info = InvoiceNumberService::getInvoiceInfo($invoiceNumber);
            
            $data[] = [
                $i,
                $invoiceNumber,
                $info ? "التسلسل: {$info['day_number']}, التاريخ: {$info['formatted_date']}" : 'غير صالح'
            ];
        }
        
        return $data;
    }
} 