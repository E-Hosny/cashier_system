<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FixInvoiceGaps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:fix-gaps {--date= : التاريخ المحدد (YYYY-MM-DD)} {--dry-run : معاينة بدون تطبيق} {--force : إجبار الإصلاح بدون تأكيد}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'إصلاح الفجوات في أرقام الفواتير وإعادة ترقيمها بشكل متسلسل';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateInput = $this->option('date');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        // تحديد التاريخ
        if ($dateInput) {
            try {
                $targetDate = Carbon::createFromFormat('Y-m-d', $dateInput);
            } catch (\Exception $e) {
                $this->error("تنسيق التاريخ غير صحيح. استخدم: YYYY-MM-DD");
                return 1;
            }
        } else {
            $targetDate = Carbon::today();
        }
        
        $this->info("إصلاح فجوات الفواتير لتاريخ: " . $targetDate->format('Y-m-d'));
        $this->newLine();
        
        // تحليل الوضع الحالي
        $analysis = $this->analyzeInvoices($targetDate);
        
        if (empty($analysis['gaps'])) {
            $this->info("✅ لا توجد فجوات في فواتير هذا التاريخ");
            return 0;
        }
        
        $this->displayAnalysis($analysis);
        
        if (!$force && !$dryRun) {
            if (!$this->confirm('هل تريد المتابعة بإصلاح الفجوات؟')) {
                $this->info("تم إلغاء العملية");
                return 0;
            }
        }
        
        // تطبيق الإصلاح
        if ($dryRun) {
            $this->info("🔍 معاينة الإصلاح (لن يتم تطبيق تغييرات فعلية):");
            $this->previewFix($analysis);
        } else {
            $this->info("🔧 تطبيق الإصلاح:");
            $result = $this->applyFix($analysis);
            $this->displayResults($result);
        }
        
        return 0;
    }
    
    /**
     * تحليل فواتير اليوم
     */
    private function analyzeInvoices(Carbon $date): array
    {
        $dateCode = $date->format('ymd');
        
        // جمع الفواتير من كلا الجدولين
        $orders = Order::whereDate('created_at', $date)
            ->whereNotNull('invoice_number')
            ->where('invoice_number', 'LIKE', $dateCode . '-%')
            ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$')
            ->orderBy('created_at')
            ->get(['id', 'invoice_number', 'created_at']);
            
                // ترتيب حسب الوقت
        $allInvoices = collect()
            ->merge($orders->map(fn($o) => ['type' => 'order', 'id' => $o->id, 'invoice_number' => $o->invoice_number, 'created_at' => $o->created_at]))
            ->sortBy('created_at')
            ->values();
        
        // استخراج الأرقام التسلسلية
        $sequences = [];
        foreach ($allInvoices as $invoice) {
            if (preg_match('/^\d{6}-(\d{3})$/', $invoice['invoice_number'], $matches)) {
                $sequences[] = (int)$matches[1];
            }
        }
        
        if (empty($sequences)) {
            return ['invoices' => $allInvoices, 'gaps' => [], 'max_sequence' => 0];
        }
        
        sort($sequences);
        $maxSequence = max($sequences);
        $minSequence = min($sequences);
        
        // البحث عن الفجوات
        $gaps = [];
        for ($i = $minSequence; $i <= $maxSequence; $i++) {
            if (!in_array($i, $sequences)) {
                $gaps[] = $i;
            }
        }
        
        return [
            'invoices' => $allInvoices,
            'gaps' => $gaps,
            'max_sequence' => $maxSequence,
            'min_sequence' => $minSequence,
            'date_code' => $dateCode
        ];
    }
    
    /**
     * عرض تحليل الوضع
     */
    private function displayAnalysis(array $analysis): void
    {
        $this->info("📊 تحليل الوضع الحالي:");
        $this->table(
            ['المؤشر', 'القيمة'],
            [
                ['إجمالي الفواتير', count($analysis['invoices'])],
                ['أصغر رقم تسلسلي', $analysis['min_sequence']],
                ['أكبر رقم تسلسلي', $analysis['max_sequence']],
                ['عدد الفجوات', count($analysis['gaps'])],
            ]
        );
        
        if (!empty($analysis['gaps'])) {
            $this->warn("⚠️  الفجوات الموجودة:");
            $gapRanges = $this->formatGaps($analysis['gaps']);
            foreach ($gapRanges as $range) {
                $this->line("  - {$range}");
            }
        }
    }
    
    /**
     * تنسيق الفجوات في نطاقات
     */
    private function formatGaps(array $gaps): array
    {
        if (empty($gaps)) return [];
        
        sort($gaps);
        $ranges = [];
        $start = $gaps[0];
        $end = $gaps[0];
        
        for ($i = 1; $i < count($gaps); $i++) {
            if ($gaps[$i] == $end + 1) {
                $end = $gaps[$i];
            } else {
                if ($start == $end) {
                    $ranges[] = (string)$start;
                } else {
                    $ranges[] = "{$start}-{$end}";
                }
                $start = $end = $gaps[$i];
            }
        }
        
        if ($start == $end) {
            $ranges[] = (string)$start;
        } else {
            $ranges[] = "{$start}-{$end}";
        }
        
        return $ranges;
    }
    
    /**
     * معاينة الإصلاح
     */
    private function previewFix(array $analysis): void
    {
        $newSequence = 1;
        
        $this->table(
            ['النوع', 'المعرف', 'الرقم القديم', 'الرقم الجديد'],
            collect($analysis['invoices'])->map(function($invoice) use (&$newSequence, $analysis) {
                $oldNumber = $invoice['invoice_number'];
                $newNumber = $analysis['date_code'] . '-' . str_pad($newSequence, 3, '0', STR_PAD_LEFT);
                $newSequence++;
                
                return [
                    $invoice['type'] == 'order' ? 'طلب عادي' : 'طلب أوفلاين',
                    $invoice['id'],
                    $oldNumber,
                    $newNumber
                ];
            })->toArray()
        );
    }
    
    /**
     * تطبيق الإصلاح
     */
    private function applyFix(array $analysis): array
    {
        $updated = ['orders' => 0];
        $errors = [];
        
        DB::transaction(function() use ($analysis, &$updated, &$errors) {
            $newSequence = 1;
            
            foreach ($analysis['invoices'] as $invoice) {
                try {
                    $newNumber = $analysis['date_code'] . '-' . str_pad($newSequence, 3, '0', STR_PAD_LEFT);
                    
                    if ($invoice['type'] == 'order') {
                        Order::where('id', $invoice['id'])
                            ->update(['invoice_number' => $newNumber]);
                        $updated['orders']++;
                    }
                    
                    $newSequence++;
                    
                } catch (\Exception $e) {
                    $errors[] = "خطأ في تحديث {$invoice['type']} رقم {$invoice['id']}: " . $e->getMessage();
                }
            }
        });
        
        return ['updated' => $updated, 'errors' => $errors];
    }
    
    /**
     * عرض النتائج
     */
    private function displayResults(array $result): void
    {
        $this->newLine();
        $this->info("✅ تم الانتهاء من الإصلاح:");
        
        $this->table(
            ['النوع', 'العدد المحدث'],
            [
                ['الطلبات العادية', $result['updated']['orders']],
                ['الإجمالي', $result['updated']['orders']]
            ]
        );
        
        if (!empty($result['errors'])) {
            $this->warn("⚠️  الأخطاء:");
            foreach ($result['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }
    }
} 