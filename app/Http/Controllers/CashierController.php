<?php

namespace App\Http\Controllers;

use App\Models\BranchFridgeStock;
use App\Models\BranchRawMaterialStock;
use App\Models\Category;
use App\Models\FridgeProductConfig;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Tenant;
use App\Support\BranchContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\StockMovement;
use App\Models\CashierShift;
use App\Services\FridgeInventoryService;
use App\Services\InvoiceNumberService;
use App\Services\OrderRefundService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Mpdf\Mpdf;

class CashierController extends Controller
{
    public function __construct(
        private FridgeInventoryService $fridgeService,
        private OrderRefundService $orderRefundService,
    ) {}

    //comment
  public function index()
{
    $products = Product::with('category')
        ->where('type', 'finished')
        ->published()
        ->latest()->get()->append('available_sizes');
    // Ensure size_variants is always an array
    $products->transform(function ($product) {
        if (is_null($product->size_variants)) {
            $product->size_variants = [];
        }
        return $product;
    });
    // فقط فئات المنتجات النهائية — لا تُعرض فئات المواد الخام على شاشة الكاشير
    $categories = Category::forProducts()->orderBy('name')->get();

    $fridgeProducts = [];
    $fridgeSectionEnabled = false;
    if (BranchContext::hasBranch()) {
        $branchId = BranchContext::requireId();
        $configs = FridgeProductConfig::query()
            ->with('product:id,name,size_variants,image')
            ->where('is_active', true)
            ->get();
        $fridgeSectionEnabled = $configs->isNotEmpty();

        $stocks = BranchFridgeStock::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy(fn ($s) => $s->product_id.'|'.$s->size);

        foreach ($configs as $config) {
            $key = $config->product_id.'|'.($config->size ?? '');
            $stock = $stocks->get($key);
            $quantity = (float) ($stock?->quantity ?? 0);

            $product = $config->product;
            if (! $product) {
                continue;
            }
            $variants = collect($product->size_variants ?? []);
            $price = 0;
            if ($config->size !== '' && $variants->isNotEmpty()) {
                $variant = $variants->firstWhere('size', $config->size);
                $price = (float) ($variant['price'] ?? 0);
            } elseif ($variants->isNotEmpty()) {
                $price = (float) ($variants->first()['price'] ?? 0);
            }
            $fridgeProducts[] = [
                'config_id' => $config->id,
                'product_id' => $config->product_id,
                'name' => $product->name,
                'size' => $config->size !== '' ? $config->size : null,
                'price' => $price,
                'fridge_quantity' => $quantity,
                'image' => $product->image,
            ];
        }
    }

    return Inertia::render('Cashier', [
        'products' => $products,
        'categories' => $categories,
        'fridgeProducts' => $fridgeProducts,
        'fridgeSectionEnabled' => $fridgeSectionEnabled,
    ]);
}


    public function store(Request $request)
    {
        $data = $request->validate([
            'total_price' => 'required|numeric',
            'payment_method' => 'required|string',
            'staff_notes' => 'nullable|string|max:1000',
            'client_request_id' => 'required|string|min:16|max:64',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.product_name' => 'required|string',
            'items.*.size' => 'nullable|string',
            'items.*.from_fridge' => 'sometimes|boolean',
        ]);

        $clientRequestId = $data['client_request_id'];
        $tenantId = Auth::user()?->tenant_id;
        $lockKey = 'order_idempotency:'.($tenantId ?? '0').':'.$clientRequestId;

        // إن وُجد طلب سابق بنفس المفتاح → أعد نجاحه بدون إنشاء فاتورة جديدة
        $existing = Order::query()
            ->where('client_request_id', $clientRequestId)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب مسبقاً',
                'order_id' => $existing->id,
                'idempotent_replay' => true,
            ]);
        }

        $draftProductIds = Product::whereIn('id', collect($data['items'])->pluck('product_id'))
            ->where('is_draft', true)
            ->pluck('id');

        if ($draftProductIds->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'أحد المنتجات في السلة غير متاح للبيع (مسودة). يرجى تحديث الصفحة.',
            ], 422);
        }

        $lock = Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            // طلب موازٍ قيد المعالجة بنفس المفتاح — انتظر النتيجة أو أعد المحاولة بنفس المفتاح
            usleep(250000);
            $existing = Order::query()
                ->where('client_request_id', $clientRequestId)
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء الطلب مسبقاً',
                    'order_id' => $existing->id,
                    'idempotent_replay' => true,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'الطلب قيد المعالجة، يرجى الانتظار ثم المحاولة بنفس العملية.',
                'processing' => true,
            ], 409);
        }

        $order = null;

        try {
            // فحص ثانٍ داخل القفل
            $existing = Order::query()
                ->where('client_request_id', $clientRequestId)
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء الطلب مسبقاً',
                    'order_id' => $existing->id,
                    'idempotent_replay' => true,
                ]);
            }

            DB::transaction(function () use ($data, $clientRequestId, &$order) {
                $activeShift = CashierShift::getActiveShift(Auth::id());

                $orderData = [
                    'total' => $data['total_price'],
                    'payment_method' => $data['payment_method'],
                    'staff_notes' => filled($data['staff_notes'] ?? null) ? trim($data['staff_notes']) : null,
                    'status' => 'completed',
                    'invoice_number' => InvoiceNumberService::generateInvoiceNumber(),
                    'client_request_id' => $clientRequestId,
                ];

                if ($activeShift) {
                    $orderData['cashier_shift_id'] = $activeShift->id;
                }

                $order = Order::create($orderData);

                $tenantId = $order->tenant_id ?? Auth::user()->tenant_id;
                $orderItems = [];
                foreach ($data['items'] as $item) {
                    $orderItems[] = [
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'size' => $item['size'],
                        'from_fridge' => ! empty($item['from_fridge']),
                        'tenant_id' => $tenantId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                OrderItem::insert($orderItems);

                $stockUpdates = [];
                $stockMovements = [];

                $productIds = collect($data['items'])->pluck('product_id')->unique();
                $products = Product::select('id', 'type', 'stock')
                    ->whereIn('id', $productIds)
                    ->get()
                    ->keyBy('id');

                $finishedProductIds = $products->where('type', 'finished')->keys();
                $ingredients = collect();
                if ($finishedProductIds->isNotEmpty()) {
                    $ingredients = DB::table('ingredients')
                        ->select('finished_product_id', 'raw_material_id', 'quantity_consumed', 'size')
                        ->whereIn('finished_product_id', $finishedProductIds)
                        ->get()
                        ->groupBy('finished_product_id');
                }

                $fridgeConfigs = FridgeProductConfig::query()
                    ->with('ingredientRules')
                    ->where('is_active', true)
                    ->get()
                    ->keyBy(fn ($c) => $c->product_id.'|'.($c->size ?? ''));

                $branchId = (int) ($order->branch_id ?? BranchContext::requireId());

                foreach ($data['items'] as $item) {
                    $product = $products->get($item['product_id']);
                    if (! $product) {
                        continue;
                    }

                    $fromFridge = ! empty($item['from_fridge']);
                    $sizeKey = (string) ($item['size'] ?? '');
                    $configKey = $product->id.'|'.$sizeKey;
                    $fridgeConfig = $fromFridge ? $fridgeConfigs->get($configKey) : null;

                    if ($fromFridge && $fridgeConfig) {
                        $qty = (float) $item['quantity'];
                        BranchFridgeStock::deduct(
                            $branchId,
                            (int) $product->id,
                            $sizeKey,
                            $qty,
                            $tenantId
                        );

                        $saleDeductions = $this->fridgeService->aggregateDeductions($fridgeConfig, 'sale', $qty);
                        foreach ($saleDeductions as $rawId => $amount) {
                            if (! isset($stockUpdates[$rawId])) {
                                $stockUpdates[$rawId] = 0;
                            }
                            $stockUpdates[$rawId] -= $amount;
                            $stockMovements[] = [
                                'product_id' => $rawId,
                                'quantity' => -$amount,
                                'type' => 'fridge_sale_ingredient',
                                'related_order_id' => $order->id,
                                'tenant_id' => $tenantId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        continue;
                    }

                    if ($product->type === 'finished') {
                        $productIngredients = $ingredients->get($product->id, collect());
                        $ingredientsForSize = $productIngredients->where('size', $item['size']);

                        foreach ($ingredientsForSize as $ingredient) {
                            $quantityToDeduct = $item['quantity'] * $ingredient->quantity_consumed;

                            if (! isset($stockUpdates[$ingredient->raw_material_id])) {
                                $stockUpdates[$ingredient->raw_material_id] = 0;
                            }
                            $stockUpdates[$ingredient->raw_material_id] -= $quantityToDeduct;

                            $stockMovements[] = [
                                'product_id' => $ingredient->raw_material_id,
                                'quantity' => -$quantityToDeduct,
                                'type' => 'sale_deduction',
                                'related_order_id' => $order->id,
                                'tenant_id' => $tenantId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    } elseif ($product->type === 'raw' && $product->stock !== null) {
                        if (! isset($stockUpdates[$product->id])) {
                            $stockUpdates[$product->id] = 0;
                        }
                        $stockUpdates[$product->id] -= $item['quantity'];

                        $stockMovements[] = [
                            'product_id' => $product->id,
                            'quantity' => -$item['quantity'],
                            'type' => 'sale_deduction',
                            'related_order_id' => $order->id,
                            'tenant_id' => $tenantId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                foreach ($stockUpdates as $productId => $change) {
                    if ($change >= 0) {
                        continue;
                    }
                    BranchRawMaterialStock::deduct(
                        $branchId,
                        (int) $productId,
                        abs($change),
                        $tenantId
                    );
                }

                foreach ($stockMovements as &$movement) {
                    $movement['branch_id'] = $branchId;
                }
                unset($movement);

                if (! empty($stockMovements)) {
                    StockMovement::insert($stockMovements);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب بنجاح!',
                'order_id' => $order->id,
            ]);
        } catch (QueryException $e) {
            // سباق نادر وصل لـ unique constraint
            if ($this->isClientRequestIdUniqueViolation($e)) {
                $existing = Order::query()
                    ->where('client_request_id', $clientRequestId)
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => true,
                        'message' => 'تم إنشاء الطلب مسبقاً',
                        'order_id' => $existing->id,
                        'idempotent_replay' => true,
                    ]);
                }
            }

            Log::error('خطأ في إنشاء الطلب: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الطلب. يرجى المحاولة مرة أخرى.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('خطأ في إنشاء الطلب: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الطلب. يرجى المحاولة مرة أخرى.',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            optional($lock)->release();
        }
    }

    protected function isClientRequestIdUniqueViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'orders_tenant_client_request_unique')
            || str_contains($message, 'client_request_id');
    }

    public function invoice($orderId)
    {
        $order = Order::with('items.product')
            ->select('id', 'total', 'created_at', 'invoice_number', 'tenant_id')
            ->findOrFail($orderId);

        $logoUrl = $this->invoiceLogoUrl($order->tenant_id);
    
        $mpdf = new Mpdf([
            'format' => [80, 297],
            'default_font' => 'Arial',
            'mode' => 'utf-8',
        ]);
    
        $html = view('invoice-html', compact('order', 'logoUrl'))->render();
        $mpdf->WriteHTML($html);
        return $mpdf->Output("invoice_{$order->id}.pdf", 'I');
    }

    public function invoiceHtml($orderId)
    {
        $order = Order::select('id', 'total', 'created_at', 'invoice_number', 'tenant_id', 'staff_notes')
            ->with([
                'items' => function ($query) {
                    $query->select('order_id', 'product_id', 'product_name', 'quantity', 'price', 'size', 'from_fridge');
                },
                'items.product.category',
            ])
            ->findOrFail($orderId);

        $logoUrl = $this->invoiceLogoUrl($order->tenant_id);
        $copy = request('copy', 'customer');
        if (! in_array($copy, ['customer', 'staff'], true)) {
            $copy = 'customer';
        }
        $qzMode = request()->boolean('qz');
        $staffHasItems = true;

        // نسخة العامل: فئات محددة بدون أسعار، واستبعاد منتجات التلاجة دائماً
        if ($copy === 'staff') {
            $branchId = BranchContext::id();
            $staffCategoryIds = [];

            if ($branchId) {
                $branch = Branch::find($branchId);
                $settings = $branch?->normalizedPrinterSettings() ?? [];
                $staffCategoryIds = $settings['staff_category_ids'] ?? [];
            }

            $order->setRelation('items', $order->items->filter(function ($item) use ($staffCategoryIds) {
                if (! empty($item->from_fridge)) {
                    return false;
                }

                if (is_array($staffCategoryIds) && ! empty($staffCategoryIds)) {
                    $categoryId = $item->product?->category_id;

                    return $categoryId !== null && in_array((int) $categoryId, $staffCategoryIds, true);
                }

                return true;
            })->values());

            $staffHasItems = $order->items->isNotEmpty();
        }

        return view('invoice-html', compact('order', 'logoUrl', 'copy', 'qzMode', 'staffHasItems'));
    }

    protected function invoiceLogoUrl(?int $tenantId): ?string
    {
        if (! $tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        return $tenant?->logo_url;
    }

    public function invoicesToday(Request $request)
    {
        $selectedDate = $request->input('date');
        
        if ($selectedDate) {
            // تحويل التاريخ المحدد إلى كائن Carbon
            $date = \Carbon\Carbon::parse($selectedDate);
            // تحديد بداية اليوم من 7 صباحا
            $start = $date->copy()->setTime(7, 0, 0);
            // تحديد نهاية اليوم في 7 صباحا لليوم التالي
            $end = $start->copy()->addDay();
        } else {
            // السلوك الافتراضي: اليوم الحالي
            $now = now();
            $start = $now->copy()->hour < 7 ? $now->copy()->subDay()->setTime(7,0,0) : $now->copy()->setTime(7,0,0);
            $end = $start->copy()->addDay();
        }

        $orders = Order::with(['items'])
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'asc')
            ->get();

        return Inertia::render('Invoices', [
            'orders' => $orders,
            'start' => $start->toDateTimeString(),
            'end' => $end->toDateTimeString(),
            'selectedDate' => $selectedDate,
        ]);
    }

    public function refundRecentOrders()
    {
        [$start, $end] = $this->businessDayRange(null);

        $orders = Order::with('items')
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Order $order) => $this->formatOrderForRefund($order));

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }

    public function refundLookup(Request $request)
    {
        $request->validate([
            'q' => 'required|string|max:64',
        ]);

        $q = $this->normalizeRefundSearchQuery($request->input('q'));
        if ($q === '') {
            return response()->json([
                'success' => false,
                'message' => 'أدخل رقم فاتورة للبحث.',
            ], 422);
        }

        $branchId = BranchContext::requireId();
        $orders = $this->findOrdersForRefundLookup($q, $branchId);

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على فاتورة بهذا الرقم في فواتير اليوم.',
            ], 404);
        }

        $formatted = $orders->map(fn (Order $order) => $this->formatOrderForRefund($order))->values();

        if ($formatted->count() === 1) {
            return response()->json([
                'success' => true,
                'order' => $formatted->first(),
                'orders' => $formatted,
                'match_count' => 1,
            ]);
        }

        return response()->json([
            'success' => true,
            'orders' => $formatted,
            'match_count' => $formatted->count(),
            'message' => "تم العثور على {$formatted->count()} فاتورة — اختر الفاتورة الصحيحة.",
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Order>
     */
    protected function findOrdersForRefundLookup(string $q, int $branchId): \Illuminate\Support\Collection
    {
        [$start, $end] = $this->businessDayRange(null);

        $base = Order::with('items')
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end]);

        $exact = (clone $base)->where('invoice_number', $q)->orderByDesc('created_at')->get();
        if ($exact->isNotEmpty()) {
            return $exact;
        }

        if (ctype_digit($q)) {
            $byId = (clone $base)->whereKey((int) $q)->get();
            if ($byId->isNotEmpty()) {
                return $byId;
            }
        }

        $partial = (clone $base)->where(function ($query) use ($q) {
            $query->where('invoice_number', 'LIKE', '%'.$q.'%');

            if (ctype_digit($q)) {
                $padded3 = str_pad($q, 3, '0', STR_PAD_LEFT);
                $query->orWhere('invoice_number', 'LIKE', '%-'.$padded3)
                    ->orWhere('invoice_number', 'LIKE', '%-'.$q);

                if (strlen($q) <= 6) {
                    $query->orWhere('invoice_number', 'LIKE', $q.'-%');
                }
            }
        })
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return $partial;
    }

    protected function normalizeRefundSearchQuery(?string $q): string
    {
        $q = trim((string) $q);
        $q = ltrim($q, '#');
        $q = preg_replace('/\s+/', '', $q) ?? $q;

        return $q;
    }

    public function refundOrder(Order $order)
    {
        abort_if((int) $order->branch_id !== (int) BranchContext::requireId(), 403);

        try {
            $refunded = $this->orderRefundService->refund($order, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'تم إرجاع الفاتورة وإعادة المخزون بنجاح.',
                'order' => $this->formatOrderForRefund($refunded),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إرجاع الفاتورة: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function businessDayRange(?string $selectedDate): array
    {
        if ($selectedDate) {
            $date = \Carbon\Carbon::parse($selectedDate);
            $start = $date->copy()->setTime(7, 0, 0);
            $end = $start->copy()->addDay();

            return [$start, $end];
        }

        $now = now();
        $start = $now->hour < 7
            ? $now->copy()->subDay()->setTime(7, 0, 0)
            : $now->copy()->setTime(7, 0, 0);

        return [$start, $start->copy()->addDay()];
    }

    protected function formatOrderForRefund(Order $order): array
    {
        return [
            'id' => $order->id,
            'invoice_number' => $order->invoice_number,
            'total' => (float) $order->total,
            'status' => $order->status,
            'is_refunded' => $order->isRefunded(),
            'can_refund' => $this->orderRefundService->canRefund($order),
            'created_at' => $order->created_at?->toIso8601String(),
            'refunded_at' => $order->refunded_at?->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->price,
                'size' => $item->size,
                'from_fridge' => (bool) $item->from_fridge,
                'line_total' => round((float) $item->price * (int) $item->quantity, 2),
            ])->values()->all(),
        ];
    }
}
