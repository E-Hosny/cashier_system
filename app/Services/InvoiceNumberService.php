<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /**
     * توليد رقم فاتورة جديد
     * 
     * @param int $tenantId
     * @return string
     */
    public static function generateInvoiceNumber($tenantId = null): string
    {
        $today = Carbon::today();
        
        // الحصول على عدد الفواتير لهذا اليوم
        $todayOrdersCount = Order::whereDate('created_at', $today)->count();
        
        // تحديد الرقم التسلسلي لهذا اليوم
        $dailySequence = $todayOrdersCount + 1;
        
        // إنشاء رقم الفاتورة بالتنسيق الجديد: YYMMDD-XXX
        $invoiceNumber = self::generateSimpleInvoiceNumber($dailySequence, $today);
        
        return $invoiceNumber;
    }
    
    /**
     * توليد رقم فاتورة بسيط بالتنسيق: YYMMDD-XXX
     * 
     * @param int $sequence
     * @param Carbon $date
     * @return string
     */
    private static function generateSimpleInvoiceNumber(int $sequence, Carbon $date): string
    {
        // تنسيق التاريخ: YYMMDD
        $dateCode = $date->format('ymd'); // مثال: 241219 لـ 19 ديسمبر 2024
        
        // تنسيق التسلسل: XXX (3 أرقام مع أصفار في البداية)
        $sequenceCode = str_pad($sequence, 3, '0', STR_PAD_LEFT);
        
        // دمج التاريخ مع التسلسل: YYMMDD-XXX
        $invoiceNumber = $dateCode . '-' . $sequenceCode;
        
        return $invoiceNumber;
    }
    
    /**
     * فك تشفير رقم الفاتورة البسيط
     * 
     * @param string $invoiceNumber
     * @return array|null
     */
    public static function decodeInvoiceNumber(string $invoiceNumber): ?array
    {
        try {
            // التحقق من تنسيق الرقم: YYMMDD-XXX
            if (!preg_match('/^\d{6}-\d{3}$/', $invoiceNumber)) {
                return null;
            }
            
            // فصل التاريخ والتسلسل
            $parts = explode('-', $invoiceNumber);
            $dateCode = $parts[0]; // YYMMDD
            $sequence = (int)$parts[1]; // XXX
            
            // تحويل التاريخ
            $year = '20' . substr($dateCode, 0, 2);
            $month = substr($dateCode, 2, 2);
            $day = substr($dateCode, 4, 2);
            
            // التحقق من صحة التاريخ
            if (!checkdate((int)$month, (int)$day, (int)$year)) {
                return null;
            }
            
            $date = Carbon::createFromFormat('Y-m-d', "{$year}-{$month}-{$day}");
            
            return [
                'sequence' => $sequence,
                'date' => $date->format('Y-m-d'),
                'day_code' => $dateCode,
                'year' => $year,
                'month' => $month,
                'day' => $day
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
    

    
    /**
     * التحقق من صحة رقم الفاتورة
     * 
     * @param string $invoiceNumber
     * @return bool
     */
    public static function isValidInvoiceNumber(string $invoiceNumber): bool
    {
        // التحقق من التنسيق: YYMMDD-XXX
        if (!preg_match('/^\d{6}-\d{3}$/', $invoiceNumber)) {
            return false;
        }
        
        return self::decodeInvoiceNumber($invoiceNumber) !== null;
    }
    
    /**
     * الحصول على معلومات الفاتورة من الرقم
     * 
     * @param string $invoiceNumber
     * @return array|null
     */
    public static function getInvoiceInfo(string $invoiceNumber): ?array
    {
        $decoded = self::decodeInvoiceNumber($invoiceNumber);
        
        if (!$decoded) {
            return null;
        }
        
        return [
            'sequence' => $decoded['sequence'],
            'date' => $decoded['date'],
            'formatted_date' => Carbon::parse($decoded['date'])->format('Y-m-d'),
            'is_today' => Carbon::parse($decoded['date'])->isToday(),
            'day_number' => $decoded['sequence'],
            'date_code' => $decoded['day_code'],
            'year' => $decoded['year'],
            'month' => $decoded['month'],
            'day' => $decoded['day']
        ];
    }
} 