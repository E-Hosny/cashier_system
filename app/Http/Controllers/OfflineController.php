<?php

namespace App\Http\Controllers;

use App\Services\OfflineService;
use App\Models\OfflineOrder;
use App\Models\OfflineCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OfflineController extends Controller
{
    /**
     * عرض صفحة إدارة الطلبات في وضع عدم الاتصال
     */
    public function index()
    {
        $stats = OfflineService::getOfflineStats();
        $pendingOrders = OfflineOrder::getPendingSync(Auth::id());
        $failedOrders = OfflineOrder::getFailedSync(Auth::id());
        
        return Inertia::render('Offline/Index', [
            'stats' => $stats,
            'pendingOrders' => $pendingOrders,
            'failedOrders' => $failedOrders,
            'isOnline' => OfflineService::isOnline(),
        ]);
    }

    /**
     * إنشاء طلب في وضع عدم الاتصال
     */
    public function store(Request $request)
    {
        try {
            \Log::info('محاولة إنشاء طلب أوفلاين', $request->all());
            
            $data = $request->validate([
                'total_price' => 'required|numeric',
                'payment_method' => 'required|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric',
                'items.*.product_name' => 'required|string',
                'items.*.size' => 'nullable|string',
            ]);

            $result = OfflineService::createOfflineOrder($data);
            
            \Log::info('نتيجة إنشاء طلب أوفلاين', $result);
            
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('خطأ في إنشاء طلب أوفلاين: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إنشاء الطلب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * مزامنة الطلبات في وضع عدم الاتصال
     */
    public function sync()
    {
        $result = OfflineService::syncOfflineOrders();
        
        // إضافة عدد الطلبات المزامنة للاستجابة
        if ($result['success']) {
            $result['synced_count'] = $result['synced_count'] ?? 0;
            $result['message'] = "تم مزامنة {$result['synced_count']} طلب بنجاح";
        }

        return response()->json($result);
    }

    /**
     * إعادة محاولة مزامنة الطلبات الفاشلة
     */
    public function retry()
    {
        $result = OfflineService::retryFailedOrders();

        return response()->json($result);
    }

    /**
     * حذف الطلبات المزامنة بنجاح
     */
    public function cleanup()
    {
        $deletedCount = OfflineService::cleanupSyncedOrders();

        return response()->json([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "تم حذف {$deletedCount} طلب مزامن بنجاح"
        ]);
    }

    /**
     * تحميل البيانات المطلوبة للعمل في وضع عدم الاتصال
     */
    public function loadData()
    {
        $data = OfflineService::loadOfflineData();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'تم تحميل البيانات بنجاح'
        ]);
    }

    /**
     * الحصول على البيانات المخزنة مؤقتاً
     */
    public function getCachedData(Request $request)
    {
        $key = $request->input('key');
        $data = OfflineService::getCachedData($key);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * التحقق من حالة الاتصال
     */
    public function checkConnection()
    {
        $isOnline = OfflineService::isOnline();
        $hasPendingOrders = OfflineService::hasPendingOrders();

        return response()->json([
            'isOnline' => $isOnline,
            'hasPendingOrders' => $hasPendingOrders,
        ]);
    }

    /**
     * الحصول على تفاصيل طلب في وضع عدم الاتصال
     */
    public function show($offlineId)
    {
        $order = OfflineOrder::where('offline_id', $offlineId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'order' => $order
        ]);
    }

    /**
     * طباعة فاتورة طلب في وضع عدم الاتصال
     */
    public function printInvoice($offlineId)
    {
        $order = OfflineOrder::where('offline_id', $offlineId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // تحويل البيانات إلى نفس شكل البيانات المستخدمة في الفاتورة العادية
        $orderData = [
            'id' => $order->offline_id,
            'invoice_number' => $order->invoice_number,
            'created_at' => $order->created_at,
            'total' => $order->total,
            'items' => $order->items,
        ];

        return view('invoice-html', ['order' => (object) $orderData]);
    }

    /**
     * طباعة فاتورة PDF لطلب في وضع عدم الاتصال
     */
    public function printInvoicePdf($offlineId)
    {
        $order = OfflineOrder::where('offline_id', $offlineId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // تحويل البيانات إلى نفس شكل البيانات المستخدمة في الفاتورة العادية
        $orderData = [
            'id' => $order->offline_id,
            'invoice_number' => $order->invoice_number,
            'created_at' => $order->created_at,
            'total' => $order->total,
            'items' => $order->items,
        ];

        $mpdf = new \Mpdf\Mpdf([
            'format' => [80, 297],
            'default_font' => 'Arial',
            'mode' => 'utf-8',
        ]);

        $html = view('invoice-html', ['order' => (object) $orderData])->render();
        $mpdf->WriteHTML($html);
        return $mpdf->Output("invoice_{$order->offline_id}.pdf", 'I');
    }



    /**
     * الحصول على إحصائيات مفصلة
     */
    public function stats()
    {
        $stats = OfflineService::getOfflineStats();
        $recentOrders = OfflineOrder::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'recentOrders' => $recentOrders
        ]);
    }

    /**
     * تصدير الطلبات في وضع عدم الاتصال
     */
    public function export(Request $request)
    {
        $status = $request->input('status', 'all');
        $userId = Auth::id();

        $query = OfflineOrder::where('user_id', $userId);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'export_date' => now()->toISOString(),
            'total_count' => $orders->count(),
            'total_amount' => $orders->sum('total'),
        ]);
    }
} 