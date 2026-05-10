<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceSequence extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'date_code',
        'current_sequence',
        'tenant_id',
        'branch_id',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

    /**
     * الحصول على الرقم التسلسلي التالي لليوم الحالي
     * مع ضمان عدم وجود race conditions
     *
     * @param  string  $dateCode
     * @param  int  $branchId
     */
    public static function getNextSequence(string $dateCode, int $branchId): int
    {
        $tenantId = auth()->user()->tenant_id;

        return DB::transaction(function () use ($dateCode, $branchId, $tenantId) {
            $sequence = self::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branchId)
                ->where('date_code', $dateCode)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                $sequence->increment('current_sequence');

                return $sequence->current_sequence;
            }

            $newSequence = self::create([
                'date_code' => $dateCode,
                'current_sequence' => 1,
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
            ]);

            return $newSequence->current_sequence;
        });
    }
    
    /**
     * إعادة تعيين متتالية اليوم الحالي بناءً على الفواتير الموجودة
     * 
     * @param string $dateCode
     * @return int
     */
    public static function resetSequenceFromExisting(string $dateCode, int $branchId, ?int $tenantId = null): int
    {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;
        if (! $tenantId) {
            throw new \InvalidArgumentException('tenant_id is required to reset invoice sequence.');
        }

        return DB::transaction(function () use ($dateCode, $branchId, $tenantId) {
            $maxFromOrders = \App\Models\Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branchId)
                ->whereNotNull('invoice_number')
                ->where('invoice_number', 'LIKE', $dateCode.'-%')
                ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$')
                ->get()
                ->pluck('invoice_number')
                ->map(function ($invoiceNumber) {
                    $parts = explode('-', $invoiceNumber);

                    return isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : 0;
                })
                ->max() ?? 0;

            self::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'date_code' => $dateCode,
                ],
                ['current_sequence' => $maxFromOrders]
            );

            return $maxFromOrders;
        });
    }
    
    /**
     * تنظيف السجلات القديمة (الاحتفاظ بآخر 90 يوم فقط)
     */
    public static function cleanupOldSequences(): int
    {
        $cutoffDate = Carbon::now()->subDays(90);
        $cutoffDateCode = $cutoffDate->format('ymd');
        
        return self::where('date_code', '<', $cutoffDateCode)->delete();
    }
} 