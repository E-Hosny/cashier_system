<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvoiceSequence;
use App\Models\Order;
use App\Models\OfflineOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InitInvoiceSequences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:init-sequences {--force : إجبار إعادة التهيئة}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تهيئة جدول متتاليات أرقام الفواتير بناءً على الفواتير الموجودة';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');
        
        $this->info("تهيئة جدول متتاليات أرقام الفواتير");
        $this->newLine();
        
        // التحقق من وجود سجلات موجودة
        $existingCount = InvoiceSequence::count();
        if ($existingCount > 0 && !$force) {
            $this->warn("يوجد {$existingCount} سجل في جدول المتتاليات.");
            if (!$this->confirm('هل تريد إعادة التهيئة؟')) {
                $this->info("تم إلغاء العملية");
                return 0;
            }
        }
        
        // مسح السجلات الموجودة إذا كان force
        if ($force || ($existingCount > 0 && $this->confirm('هل تريد مسح السجلات الموجودة؟'))) {
            InvoiceSequence::truncate();
            $this->info("تم مسح السجلات الموجودة");
        }
        
        // جمع جميع التواريخ المختلفة من الفواتير
        $this->info("جمع التواريخ من الفواتير الموجودة...");
        
        $dates = collect();
        
        // التواريخ من جدول orders
        $orderDates = Order::whereNotNull('invoice_number')
            ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$')
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->pluck('date');
        
        $dates = $dates->merge($orderDates);
        
        // التواريخ من جدول offline_orders
        $offlineOrderDates = OfflineOrder::whereNotNull('invoice_number')
            ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$')
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->pluck('date');
        
        $dates = $dates->merge($offlineOrderDates)->unique()->sort();
        
        $this->info("وجدت " . $dates->count() . " تاريخ مختلف");
        
        if ($dates->isEmpty()) {
            $this->info("لا توجد فواتير للمعالجة");
            return 0;
        }
        
        // معالجة كل تاريخ
        $processedCount = 0;
        $this->info("معالجة التواريخ...");
        
        $progressBar = $this->output->createProgressBar($dates->count());
        $progressBar->start();
        
        foreach ($dates as $date) {
            try {
                $dateCode = Carbon::parse($date)->format('ymd');
                $maxSequence = InvoiceSequence::resetSequenceFromExisting($dateCode);
                
                $this->line("  {$date} ({$dateCode}): {$maxSequence}");
                $processedCount++;
                
            } catch (\Exception $e) {
                $this->error("خطأ في معالجة تاريخ {$date}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        // عرض النتائج
        $this->info("✅ تم الانتهاء من التهيئة:");
        $this->table(
            ['المؤشر', 'القيمة'],
            [
                ['التواريخ المعالجة', $processedCount],
                ['السجلات المنشأة', InvoiceSequence::count()],
            ]
        );
        
        // عرض آخر 10 سجلات
        $this->info("آخر 10 سجلات:");
        $recent = InvoiceSequence::latest()->take(10)->get();
        
        $this->table(
            ['كود التاريخ', 'آخر رقم تسلسلي', 'تاريخ الإنشاء'],
            $recent->map(function($seq) {
                $date = Carbon::createFromFormat('ymd', $seq->date_code)->format('Y-m-d');
                return [
                    $seq->date_code . " ({$date})",
                    $seq->current_sequence,
                    $seq->created_at->format('Y-m-d H:i:s')
                ];
            })->toArray()
        );
        
        return 0;
    }
} 