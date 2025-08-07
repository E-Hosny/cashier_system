<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InvoiceNumberService;
use App\Models\Order;
use App\Models\OfflineOrder;
use Carbon\Carbon;

class TestInvoiceSequence extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:test-sequence {--count=10 : عدد الفواتير للاختبار} {--parallel=false : اختبار التوليد المتوازي}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'اختبار تسلسل أرقام الفواتير والتأكد من عدم وجود فجوات';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->option('count');
        $parallel = $this->option('parallel');
        
        $this->info("اختبار تسلسل أرقام الفواتير - توليد {$count} رقم فاتورة");
        $this->newLine();
        
        if ($parallel) {
            $this->testParallelGeneration($count);
        } else {
            $this->testSequentialGeneration($count);
        }
        
        $this->testSequenceGaps();
        $this->showTodaysInvoices();
    }
    
    /**
     * اختبار التوليد المتسلسل
     */
    private function testSequentialGeneration($count)
    {
        $this->info("🔄 اختبار التوليد المتسلسل:");
        
        $invoices = [];
        for ($i = 1; $i <= $count; $i++) {
            $invoice = InvoiceNumberService::generateInvoiceNumber();
            $invoices[] = $invoice;
            $this->line("  {$i}. {$invoice}");
        }
        
        $this->checkSequenceIntegrity($invoices);
    }
    
    /**
     * اختبار التوليد المتوازي (محاكاة)
     */
    private function testParallelGeneration($count)
    {
        $this->info("⚡ اختبار التوليد المتوازي:");
        
        $invoices = [];
        
        // محاكاة التوليد المتوازي باستخدام حلقات متداخلة
        for ($batch = 1; $batch <= ceil($count / 3); $batch++) {
            $batchInvoices = [];
            
            for ($i = 1; $i <= min(3, $count - ($batch - 1) * 3); $i++) {
                $invoice = InvoiceNumberService::generateInvoiceNumber();
                $batchInvoices[] = $invoice;
                $invoices[] = $invoice;
            }
            
            $this->line("  الدفعة {$batch}: " . implode(', ', $batchInvoices));
        }
        
        $this->checkSequenceIntegrity($invoices);
    }
    
    /**
     * التحقق من سلامة التسلسل
     */
    private function checkSequenceIntegrity($invoices)
    {
        $this->newLine();
        $this->info("🔍 فحص سلامة التسلسل:");
        
        // التحقق من عدم وجود تكرار
        $duplicates = array_diff_assoc($invoices, array_unique($invoices));
        if (!empty($duplicates)) {
            $this->error("❌ وجدت أرقام مكررة: " . implode(', ', $duplicates));
        } else {
            $this->info("✅ لا توجد أرقام مكررة");
        }
        
        // استخراج الأرقام التسلسلية
        $sequences = [];
        foreach ($invoices as $invoice) {
            if (preg_match('/^\d{6}-(\d{3})$/', $invoice, $matches)) {
                $sequences[] = (int)$matches[1];
            }
        }
        
        if (!empty($sequences)) {
            sort($sequences);
            $this->info("الأرقام التسلسلية: " . implode(', ', $sequences));
            
            // التحقق من التسلسل
            $gaps = [];
            for ($i = 1; $i < count($sequences); $i++) {
                $expected = $sequences[$i-1] + 1;
                if ($sequences[$i] != $expected) {
                    $gaps[] = "فجوة بين {$sequences[$i-1]} و {$sequences[$i]}";
                }
            }
            
            if (!empty($gaps)) {
                $this->warn("⚠️  وجدت فجوات في التسلسل:");
                foreach ($gaps as $gap) {
                    $this->line("  - {$gap}");
                }
            } else {
                $this->info("✅ التسلسل صحيح بدون فجوات");
            }
        }
    }
    
    /**
     * اختبار وجود فجوات في النظام الحالي
     */
    private function testSequenceGaps()
    {
        $this->newLine();
        $this->info("🔍 فحص الفجوات في النظام الحالي:");
        
        $today = Carbon::today();
        $dateCode = $today->format('ymd');
        
        // جمع جميع أرقام اليوم
        $orderInvoices = Order::whereDate('created_at', $today)
            ->whereNotNull('invoice_number')
            ->where('invoice_number', 'LIKE', $dateCode . '-%')
            ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$')
            ->pluck('invoice_number')
            ->toArray();
            
        $offlineInvoices = OfflineOrder::whereDate('created_at', $today)
            ->whereNotNull('invoice_number')
            ->where('invoice_number', 'LIKE', $dateCode . '-%')
            ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$')
            ->pluck('invoice_number')
            ->toArray();
        
        $allInvoices = array_merge($orderInvoices, $offlineInvoices);
        
        if (empty($allInvoices)) {
            $this->info("لا توجد فواتير لليوم الحالي");
            return;
        }
        
        // استخراج الأرقام التسلسلية
        $sequences = [];
        foreach ($allInvoices as $invoice) {
            if (preg_match('/^\d{6}-(\d{3})$/', $invoice, $matches)) {
                $sequences[] = (int)$matches[1];
            }
        }
        
        sort($sequences);
        $this->info("إجمالي فواتير اليوم: " . count($sequences));
        $this->info("النطاق: " . min($sequences) . " - " . max($sequences));
        
        // البحث عن الفجوات
        $gaps = [];
        for ($i = min($sequences); $i <= max($sequences); $i++) {
            if (!in_array($i, $sequences)) {
                $gaps[] = $i;
            }
        }
        
        if (!empty($gaps)) {
            $this->warn("⚠️  وجدت " . count($gaps) . " فجوة في التسلسل:");
            $this->line("الأرقام المفقودة: " . implode(', ', $gaps));
        } else {
            $this->info("✅ التسلسل مكتمل بدون فجوات");
        }
    }
    
    /**
     * عرض فواتير اليوم
     */
    private function showTodaysInvoices()
    {
        $this->newLine();
        $this->info("📋 فواتير اليوم الحالي:");
        
        $today = Carbon::today();
        $dateCode = $today->format('ymd');
        
        $orders = Order::whereDate('created_at', $today)
            ->whereNotNull('invoice_number')
            ->orderBy('invoice_number')
            ->pluck('invoice_number', 'id');
            
        $offlineOrders = OfflineOrder::whereDate('created_at', $today)
            ->whereNotNull('invoice_number')
            ->orderBy('invoice_number')
            ->pluck('invoice_number', 'id');
        
        $this->table(
            ['النوع', 'العدد'],
            [
                ['الطلبات العادية', $orders->count()],
                ['الطلبات الأوفلاين', $offlineOrders->count()],
                ['الإجمالي', $orders->count() + $offlineOrders->count()]
            ]
        );
        
        if ($orders->count() > 0 || $offlineOrders->count() > 0) {
            $allInvoices = $orders->merge($offlineOrders)->sort();
            $this->line("آخر 10 فواتير:");
            foreach ($allInvoices->take(-10) as $invoice) {
                $this->line("  - {$invoice}");
            }
        }
    }
} 