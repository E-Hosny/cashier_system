<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceSequence extends Model
{
    protected $fillable = [
        'date_code',
        'current_sequence'
    ];

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
            // محاولة الحصول على السجل الموجود مع قفل للتحديث
            $sequence = self::where('date_code', $dateCode)
                ->lockForUpdate()
                ->first();
            
            if ($sequence) {
                // تحديث الرقم التسلسلي الحالي
                $sequence->increment('current_sequence');
                return $sequence->current_sequence;
            } else {
                // إنشاء سجل جديد لهذا اليوم
                $newSequence = self::create([
                    'date_code' => $dateCode,
                    'current_sequence' => 1
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
            // حساب أعلى رقم تسلسلي من الفواتير الموجودة
            $maxFromOrders = \App\Models\Order::whereNotNull('invoice_number')
                ->where('invoice_number', 'LIKE', $dateCode . '-%')
                ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$')
                ->get()
                ->pluck('invoice_number')
                ->map(function($invoiceNumber) {
                    $parts = explode('-', $invoiceNumber);
                    return isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 0;
                })
                ->max() ?? 0;
            
            $maxFromOfflineOrders = \App\Models\OfflineOrder::whereNotNull('invoice_number')
                ->where('invoice_number', 'LIKE', $dateCode . '-%')
                ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$')
                ->get()
                ->pluck('invoice_number')
                ->map(function($invoiceNumber) {
                    $parts = explode('-', $invoiceNumber);
                    return isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 0;
                })
                ->max() ?? 0;
            
            $maxSequence = max($maxFromOrders, $maxFromOfflineOrders);
            
            // تحديث أو إنشاء السجل
            self::updateOrCreate(
                ['date_code' => $dateCode],
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