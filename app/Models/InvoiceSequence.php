<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceSequence extends Model
{
    protected $fillable = [
        'date_code',
        'current_sequence',
        'tenant_id'
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (auth()->check()) {
                $query->where('tenant_id', auth()->user()->tenant_id);
            }
        });

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    /**
     * الحصول على الرقم التسلسلي التالي لليوم الحالي
     * مع ضمان عدم وجود race conditions
     * 
     * @param string $dateCode
     * @return int
     */
    public static function getNextSequence(string $dateCode): int
    {
        return DB::transaction(function () use ($dateCode) {
            $tenantId = auth()->check() ? auth()->user()->tenant_id : null;
            
            // محاولة الحصول على السجل الموجود مع قفل للتحديث
            $query = self::where('date_code', $dateCode)->lockForUpdate();
            
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            
            $sequence = $query->first();
            
            if ($sequence) {
                // تحديث الرقم التسلسلي الحالي
                $sequence->increment('current_sequence');
                return $sequence->current_sequence;
            } else {
                // إنشاء سجل جديد لهذا اليوم
                $newSequence = self::create([
                    'date_code' => $dateCode,
                    'current_sequence' => 1,
                    'tenant_id' => $tenantId
                ]);
                return $newSequence->current_sequence;
            }
        });
    }
    
    /**
     * إعادة تعيين متتالية اليوم الحالي بناءً على الفواتير الموجودة
     * 
     * @param string $dateCode
     * @return int
     */
    public static function resetSequenceFromExisting(string $dateCode): int
    {
        return DB::transaction(function () use ($dateCode) {
            $tenantId = auth()->check() ? auth()->user()->tenant_id : null;
            
            // حساب أعلى رقم تسلسلي من الفواتير الموجودة
            $query = \App\Models\Order::whereNotNull('invoice_number')
                ->where('invoice_number', 'LIKE', $dateCode . '-%')
                ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$');
            
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            
            $maxFromOrders = $query->get()
                ->pluck('invoice_number')
                ->map(function($invoiceNumber) {
                    $parts = explode('-', $invoiceNumber);
                    return isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 0;
                })
                ->max() ?? 0;
            
            $maxSequence = $maxFromOrders;
            
            // تحديث أو إنشاء السجل
            $updateData = ['date_code' => $dateCode];
            if ($tenantId) {
                $updateData['tenant_id'] = $tenantId;
            }
            
            self::updateOrCreate(
                $updateData,
                ['current_sequence' => $maxSequence]
            );
            
            return $maxSequence;
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