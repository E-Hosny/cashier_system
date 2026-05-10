<?php

namespace App\Console\Commands;

use App\Models\InvoiceSequence;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class InitInvoiceSequences extends Command
{
    protected $signature = 'invoices:init-sequences {--force : إجبار إعادة التهيئة}';

    protected $description = 'تهيئة جدول متتاليات أرقام الفواتير بناءً على الفواتير الموجودة (لكل مستأجر وفرع)';

    public function handle()
    {
        $force = $this->option('force');

        $this->info('تهيئة جدول متتاليات أرقام الفواتير');
        $this->newLine();

        $existingCount = InvoiceSequence::withoutGlobalScopes()->count();
        if ($existingCount > 0 && ! $force) {
            $this->warn("يوجد {$existingCount} سجل في جدول المتتاليات.");
            if (! $this->confirm('هل تريد إعادة التهيئة؟')) {
                $this->info('تم إلغاء العملية');

                return 0;
            }
        }

        if ($force || ($existingCount > 0 && $this->confirm('هل تريد مسح السجلات الموجودة؟'))) {
            InvoiceSequence::withoutGlobalScopes()->truncate();
            $this->info('تم مسح السجلات الموجودة');
        }

        $pairs = Order::withoutGlobalScopes()
            ->whereNotNull('invoice_number')
            ->whereNotNull('branch_id')
            ->whereNotNull('tenant_id')
            ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$')
            ->selectRaw('DISTINCT tenant_id, branch_id')
            ->get();

        if ($pairs->isEmpty()) {
            $this->info('لا توجد فواتير للمعالجة');

            return 0;
        }

        $processed = 0;
        foreach ($pairs as $row) {
            $dateCodes = Order::withoutGlobalScopes()
                ->where('tenant_id', $row->tenant_id)
                ->where('branch_id', $row->branch_id)
                ->whereNotNull('invoice_number')
                ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$')
                ->pluck('invoice_number')
                ->map(fn ($n) => substr($n, 0, 6))
                ->unique();

            foreach ($dateCodes as $dateCode) {
                try {
                    InvoiceSequence::resetSequenceFromExisting($dateCode, (int) $row->branch_id, (int) $row->tenant_id);
                    $processed++;
                    $dateHuman = Carbon::createFromFormat('ymd', $dateCode)->format('Y-m-d');
                    $this->line("  tenant {$row->tenant_id} branch {$row->branch_id} {$dateCode} ({$dateHuman})");
                } catch (\Throwable $e) {
                    $this->error("خطأ tenant {$row->tenant_id} branch {$row->branch_id} {$dateCode}: ".$e->getMessage());
                }
            }
        }

        $this->newLine();
        $this->info('✅ تم الانتهاء من التهيئة.');
        $this->table(
            ['المؤشر', 'القيمة'],
            [
                ['عمليات المزامنة', $processed],
                ['السجلات', InvoiceSequence::withoutGlobalScopes()->count()],
            ]
        );

        return 0;
    }
}
