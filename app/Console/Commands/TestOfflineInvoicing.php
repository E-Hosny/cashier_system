<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InvoiceNumberService;
use App\Services\OfflineService;
use App\Models\OfflineOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TestOfflineInvoicing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:test-offline {--user-id=1 : معرف المستخدم للاختبار}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'اختبار النظام الأوفلاين مع ترقيم الفواتير';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = (int) $this->option('user-id');
        
        // التحقق من وجود المستخدم
        $user = User::find($userId);
        if (!$user) {
            $this->error("المستخدم برقم {$userId} غير موجود");
            return 1;
        }
        
        $this->info("اختبار النظام الأوفلاين مع ترقيم الفواتير");
        $this->info("المستخدم: {$user->name} (ID: {$user->id})");
        $this->newLine();
        
        // تسجيل دخول المستخدم للاختبار
        Auth::login($user);
        
        // السيناريو 1: إنشاء طلبات أوفلاين
        $this->testOfflineOrderCreation();
        
        // السيناريو 2: اختبار التسلسل المختلط
        $this->testMixedSequencing();
        
        // السيناريو 3: اختبار المزامنة
        $this->testOfflineSync();
        
        // السيناريو 4: التحقق من النتائج النهائية
        $this->verifyResults();
        
        return 0;
    }
    
    /**
     * اختبار إنشاء طلبات أوفلاين
     */
    private function testOfflineOrderCreation()
    {
        $this->info("🔄 السيناريو 1: إنشاء طلبات أوفلاين");
        
        $offlineData = [
            'total_price' => 25.50,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 1,
                    'price' => 25.50,
                    'product_name' => 'قهوة تركية',
                    'size' => 'كبير'
                ]
            ]
        ];
        
        $invoices = [];
        for ($i = 1; $i <= 3; $i++) {
            try {
                $result = OfflineService::createOfflineOrder($offlineData);
                
                if ($result['success']) {
                    $invoices[] = $result['invoice_number'];
                    $this->line("  ✅ طلب أوفلاين {$i}: {$result['invoice_number']}");
                } else {
                    $this->error("  ❌ فشل طلب أوفلاين {$i}: {$result['message']}");
                }
            } catch (\Exception $e) {
                $this->error("  ❌ خطأ في طلب أوفلاين {$i}: " . $e->getMessage());
            }
        }
        
        if (count($invoices) === 3) {
            $this->info("  ✅ تم إنشاء 3 طلبات أوفلاين بنجاح");
            $this->checkSequenceIntegrity($invoices, "الطلبات الأوفلاين");
        }
        
        $this->newLine();
    }
    
    /**
     * اختبار التسلسل المختلط
     */
    private function testMixedSequencing()
    {
        $this->info("🔄 السيناريو 2: اختبار التسلسل المختلط (عادي + أوفلاين)");
        
        $invoices = [];
        
        // إنشاء طلبات عادية محاكاة
        $this->line("  📱 إنشاء طلبات عادية:");
        for ($i = 1; $i <= 2; $i++) {
            $invoice = InvoiceNumberService::generateInvoiceNumber();
            $invoices[] = $invoice;
            $this->line("    طلب عادي {$i}: {$invoice}");
        }
        
        // إنشاء طلبات أوفلاين
        $this->line("  📴 إنشاء طلبات أوفلاين:");
        $offlineData = [
            'total_price' => 15.00,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => 2,
                    'quantity' => 1,
                    'price' => 15.00,
                    'product_name' => 'شاي بالنعناع',
                    'size' => 'وسط'
                ]
            ]
        ];
        
        for ($i = 1; $i <= 2; $i++) {
            try {
                $result = OfflineService::createOfflineOrder($offlineData);
                if ($result['success']) {
                    $invoices[] = $result['invoice_number'];
                    $this->line("    طلب أوفلاين {$i}: {$result['invoice_number']}");
                }
            } catch (\Exception $e) {
                $this->line("    خطأ: " . $e->getMessage());
            }
        }
        
        // طلب عادي أخير
        $this->line("  📱 طلب عادي أخير:");
        $invoice = InvoiceNumberService::generateInvoiceNumber();
        $invoices[] = $invoice;
        $this->line("    طلب عادي أخير: {$invoice}");
        
        $this->checkSequenceIntegrity($invoices, "التسلسل المختلط");
        $this->newLine();
    }
    
    /**
     * اختبار المزامنة
     */
    private function testOfflineSync()
    {
        $this->info("🔄 السيناريو 3: اختبار مزامنة الطلبات الأوفلاين");
        
        // عرض الطلبات المعلقة
        $pendingOrders = OfflineOrder::where('status', 'pending_sync')
            ->where('user_id', Auth::id())
            ->get();
            
        $this->line("  📋 الطلبات المعلقة للمزامنة: " . $pendingOrders->count());
        
        foreach ($pendingOrders as $order) {
            $this->line("    - {$order->invoice_number} ({$order->total} جنيه)");
        }
        
        if ($pendingOrders->count() > 0) {
            $this->line("  🔄 تشغيل المزامنة...");
            
            try {
                $result = OfflineService::syncOfflineOrders();
                
                if ($result['success']) {
                    $this->info("  ✅ تم مزامنة {$result['synced_count']} طلب بنجاح");
                    
                    if ($result['failed_count'] > 0) {
                        $this->warn("  ⚠️  فشل {$result['failed_count']} طلب");
                        foreach ($result['errors'] as $error) {
                            $this->line("    - {$error}");
                        }
                    }
                } else {
                    $this->error("  ❌ فشلت المزامنة");
                }
            } catch (\Exception $e) {
                $this->error("  ❌ خطأ في المزامنة: " . $e->getMessage());
            }
        } else {
            $this->line("  ℹ️  لا توجد طلبات معلقة للمزامنة");
        }
        
        $this->newLine();
    }
    
    /**
     * التحقق من النتائج النهائية
     */
    private function verifyResults()
    {
        $this->info("📊 النتائج النهائية:");
        
        // إحصائيات الطلبات الأوفلاين
        $offlineStats = OfflineOrder::getStats(Auth::id());
        
        $this->table(
            ['النوع', 'العدد'],
            [
                ['إجمالي الطلبات الأوفلاين', $offlineStats['total']],
                ['معلق للمزامنة', $offlineStats['pending']],
                ['تم مزامنته', $offlineStats['synced']],
                ['فشل', $offlineStats['failed']],
                ['إجمالي المبلغ', $offlineStats['total_amount'] . ' جنيه']
            ]
        );
        
        // التحقق من تسلسل أرقام اليوم
        $today = now()->format('ymd');
        
        $orderInvoices = Order::whereDate('created_at', today())
            ->whereNotNull('invoice_number')
            ->where('invoice_number', 'LIKE', $today . '-%')
            ->orderBy('invoice_number')
            ->pluck('invoice_number');
            
        $offlineInvoices = OfflineOrder::whereDate('created_at', today())
            ->whereNotNull('invoice_number')
            ->where('invoice_number', 'LIKE', $today . '-%')
            ->orderBy('invoice_number')
            ->pluck('invoice_number');
        
        $allInvoices = $orderInvoices->merge($offlineInvoices)->sort()->values();
        
        $this->info("أرقام الفواتير اليوم ({$allInvoices->count()}):");
        foreach ($allInvoices->take(10) as $invoice) {
            $this->line("  - {$invoice}");
        }
        
        if ($allInvoices->count() > 10) {
            $this->line("  ... و " . ($allInvoices->count() - 10) . " فاتورة أخرى");
        }
        
        // التحقق من وجود فجوات
        $sequences = $allInvoices->map(function($invoice) {
            $parts = explode('-', $invoice);
            return isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 0;
        })->filter()->sort()->values();
        
        if ($sequences->count() > 0) {
            $gaps = [];
            for ($i = $sequences->min(); $i <= $sequences->max(); $i++) {
                if (!$sequences->contains($i)) {
                    $gaps[] = $i;
                }
            }
            
            if (empty($gaps)) {
                $this->info("✅ التسلسل مكتمل بدون فجوات!");
            } else {
                $this->warn("⚠️  وجدت " . count($gaps) . " فجوة: " . implode(', ', $gaps));
            }
        }
    }
    
    /**
     * التحقق من سلامة التسلسل
     */
    private function checkSequenceIntegrity($invoices, $context)
    {
        if (empty($invoices)) {
            return;
        }
        
        // استخراج الأرقام التسلسلية
        $sequences = [];
        foreach ($invoices as $invoice) {
            if (preg_match('/^\d{6}-(\d{3})$/', $invoice, $matches)) {
                $sequences[] = (int)$matches[1];
            }
        }
        
        if (empty($sequences)) {
            return;
        }
        
        sort($sequences);
        
        // التحقق من عدم وجود تكرار
        if (count($sequences) !== count(array_unique($sequences))) {
            $this->error("  ❌ {$context}: وجدت أرقام مكررة");
        } else {
            $this->info("  ✅ {$context}: لا توجد أرقام مكررة");
        }
        
        // التحقق من التسلسل
        $gaps = [];
        for ($i = 1; $i < count($sequences); $i++) {
            if ($sequences[$i] !== $sequences[$i-1] + 1) {
                $gaps[] = "فجوة بين {$sequences[$i-1]} و {$sequences[$i]}";
            }
        }
        
        if (!empty($gaps)) {
            $this->warn("  ⚠️  {$context}: " . implode(', ', $gaps));
        } else {
            $this->info("  ✅ {$context}: التسلسل صحيح");
        }
    }
} 