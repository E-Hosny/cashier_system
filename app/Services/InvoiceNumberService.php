<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OfflineOrder;
use App\Models\InvoiceSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /**
     * توليد رقم فاتورة جديد بتسلسل صحيح بدون فجوات
     * 
     * @param int $tenantId
     * @return string
     */
    public static function generateInvoiceNumber($tenantId = null): string
    {
        // التحقق من قفل نظام الفواتير أثناء المزامنة
        $invoiceSystemLockKey = "invoice_numbering_system_lock";
        
        if (\Illuminate\Support\Facades\Cache::has($invoiceSystemLockKey)) {
            // إذا كان النظام مقفل للمزامنة، انتظار قصير ثم المحاولة مرة أخرى
            $attempts = 0;
            $maxAttempts = 10; // انتظار 10 ثوان كحد أقصى
            
            while (\Illuminate\Support\Facades\Cache::has($invoiceSystemLockKey) && $attempts < $maxAttempts) {
                sleep(1);
                $attempts++;
            }
            
            // إذا لم يتم تحرير القفل، استخدم آلية طوارئ
            if (\Illuminate\Support\Facades\Cache::has($invoiceSystemLockKey)) {
                return self::generateEmergencyInvoiceNumber();
            }
        }
        
        // قفل إضافي لمنع التضارب في توليد الأرقام
        $numberingLockKey = "invoice_numbering_lock_" . time();
        if (!\Illuminate\Support\Facades\Cache::add($numberingLockKey, true, 30)) {
            // إذا فشل في الحصول على القفل، استخدم آلية طوارئ
            return self::generateEmergencyInvoiceNumber();
        }
        
        try {
            $today = Carbon::today();
            $dateCode = $today->format('ymd'); // مثال: 250806 لـ 6 أغسطس 2025
            
            // الحصول على الرقم التسلسلي التالي باستخدام النظام الآمن
            $nextSequence = InvoiceSequence::getNextSequence($dateCode);
            
            // التحقق من عدم وجود تضارب
            $invoiceNumber = $dateCode . '-' . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
            
            // التحقق النهائي من عدم وجود الرقم
            if (self::invoiceNumberExists($invoiceNumber)) {
                // إذا كان الرقم موجود، حاول مرة أخرى
                $nextSequence = InvoiceSequence::getNextSequence($dateCode);
                $invoiceNumber = $dateCode . '-' . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
                
                // إذا فشل مرة أخرى، استخدم آلية طوارئ
                if (self::invoiceNumberExists($invoiceNumber)) {
                    return self::generateEmergencyInvoiceNumber();
                }
            }
            
            return $invoiceNumber;
            
        } finally {
            // إزالة قفل الترقيم
            \Illuminate\Support\Facades\Cache::forget($numberingLockKey);
        }
    }
    
    /**
     * توليد رقم فاتورة طوارئ في حالة قفل النظام
     * 
     * @return string
     */
    private static function generateEmergencyInvoiceNumber(): string
    {
        $today = Carbon::today();
        $dateCode = $today->format('ymd');
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        
        // تنسيق طوارئ: YYMMDD-EMG-TIMESTAMP-RANDOM
        return $dateCode . '-EMG-' . $timestamp . '-' . $random;
    }
    
    /**
     * الحصول على أعلى رقم تسلسلي لهذا اليوم من كلا الجدولين
     * 
     * @param string $dateCode
     * @return int
     */
    private static function getMaxSequenceForToday(string $dateCode): int
    {
        // البحث عن أعلى رقم تسلسلي في جدول orders
        $ordersQuery = Order::whereNotNull('invoice_number')
            ->where('invoice_number', 'LIKE', $dateCode . '-%')
            ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$') // فقط الأرقام العادية
            ->lockForUpdate()
            ->pluck('invoice_number');
        
        $maxFromOrders = $ordersQuery
            ->map(function($invoiceNumber) {
                $parts = explode('-', $invoiceNumber);
                return isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 0;
            })
            ->max() ?? 0;
        
        // البحث عن أعلى رقم تسلسلي في جدول offline_orders  
        $offlineOrdersQuery = OfflineOrder::whereNotNull('invoice_number')
            ->where('invoice_number', 'LIKE', $dateCode . '-%')
            ->where('invoice_number', 'REGEXP', '^[0-9]{6}-[0-9]{3}$') // فقط الأرقام العادية
            ->lockForUpdate()
            ->pluck('invoice_number');
            
        $maxFromOfflineOrders = $offlineOrdersQuery
            ->map(function($invoiceNumber) {
                $parts = explode('-', $invoiceNumber);
                return isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 0;
            })
            ->max() ?? 0;
        
        return max($maxFromOrders, $maxFromOfflineOrders);
    }
    
    /**
     * التحقق من وجود رقم الفاتورة
     * 
     * @param string $invoiceNumber
     * @return bool
     */
    private static function invoiceNumberExists(string $invoiceNumber): bool
    {
        return Order::where('invoice_number', $invoiceNumber)->exists() || 
               OfflineOrder::where('invoice_number', $invoiceNumber)->exists();
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