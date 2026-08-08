<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\SalaryDelivery;
use Illuminate\Console\Command;

class FixZeroSalaryDeliveries extends Command
{
    protected $signature = 'salaries:fix-zero-deliveries
                            {--dry-run : معاينة بدون حفظ}
                            {--force : تنفيذ بدون تأكيد}';

    protected $description = 'تصحيح تسليمات الرواتب المحفوظة بمبلغ صفر باستخدام حساب يوم العمل (7 ص → 7 ص)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $deliveries = SalaryDelivery::withoutGlobalScopes()
            ->with(['employee' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('status', 'delivered')
            ->where(function ($q) {
                $q->where('total_amount', 0)
                    ->orWhereNull('total_amount');
            })
            ->orderBy('salary_date')
            ->get();

        if ($deliveries->isEmpty()) {
            $this->info('لا توجد تسليمات بمبلغ صفر تحتاج تصحيحاً.');

            return self::SUCCESS;
        }

        $this->info('عدد السجلات المرشحة: '.$deliveries->count());

        $rows = [];
        $toUpdate = [];

        foreach ($deliveries as $delivery) {
            $employee = $delivery->employee;
            if (! $employee) {
                continue;
            }

            $anchor = $delivery->salary_date?->toDateString() ?? (string) $delivery->getRawOriginal('salary_date');
            $hours = round($employee->getHoursForBusinessDayAnchor($anchor), 2);
            $amount = round($employee->getAmountForBusinessDayAnchor($anchor), 2);

            $rows[] = [
                $delivery->id,
                $employee->name,
                $anchor,
                (string) $delivery->total_amount,
                number_format($hours, 2),
                number_format($amount, 2),
                optional($delivery->delivered_at)?->toDateTimeString() ?? '',
            ];

            if ($amount > 0) {
                $toUpdate[] = [$delivery, $hours, $amount];
            }
        }

        $this->table(
            ['ID', 'الموظف', 'يوم العمل', 'المبلغ الحالي', 'الساعات الصحيحة', 'المبلغ الصحيح', 'وقت التسليم'],
            $rows
        );

        if ($toUpdate === []) {
            $this->warn('لا توجد سجلات يمكن إعادة حساب مبلغها (ربما لا يوجد حضور مكتمل).');

            return self::SUCCESS;
        }

        $this->info('سيتم تصحيح '.count($toUpdate).' سجل. وقت التسليم (delivered_at) لن يتغير.');

        if ($dryRun) {
            $this->comment('وضع المعاينة: لم يتم حفظ أي تغيير.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('تطبيق التصحيح؟', true)) {
            $this->warn('تم الإلغاء.');

            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($toUpdate as [$delivery, $hours, $amount]) {
            $delivery->update([
                'hours_worked' => $hours,
                'hourly_rate' => $delivery->employee->hourly_rate,
                'total_amount' => $amount,
            ]);
            $fixed++;
        }

        $this->info("تم تصحيح {$fixed} سجل تسليم.");

        return self::SUCCESS;
    }
}
