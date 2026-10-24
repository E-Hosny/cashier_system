<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Models\SupplierPurchaseLine;
use App\Services\SupplierPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->abortUnlessCanView();

        $search = trim((string) $request->input('search', ''));

        $suppliers = Supplier::query()
            ->withSum('purchases as purchases_total', 'total_amount')
            ->withSum('payments as payments_total', 'amount')
            ->withCount('purchases')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_person' => $supplier->contact_person,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'is_active' => (bool) $supplier->is_active,
                'purchases_count' => (int) $supplier->purchases_count,
                'purchases_total' => round((float) $supplier->purchases_total, 2),
                'payments_total' => round((float) $supplier->payments_total, 2),
                'remaining' => round((float) $supplier->purchases_total - (float) $supplier->payments_total, 2),
            ]);

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => $suppliers,
            'search' => $search,
            'canManage' => $this->canManage(),
            'canViewPayments' => $this->canViewPayments(),
        ]);
    }

    public function create(): Response
    {
        $this->abortUnlessCanManage();

        return Inertia::render('Admin/Suppliers/Form', [
            'supplier' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->abortUnlessCanManage();

        $data = $this->validateSupplier($request);
        $supplier = Supplier::create($data);

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', 'تم إضافة المورد.');
    }

    public function show(Supplier $supplier): Response
    {
        $this->abortUnlessCanView();

        $purchases = $supplier->purchases()
            ->with(['lines.branch:id,name'])
            ->orderByDesc('purchased_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SupplierPurchase $purchase) => $this->purchasePayload($purchase));

        return Inertia::render('Admin/Suppliers/Show', [
            'supplier' => $this->supplierPayload($supplier),
            'purchases' => $purchases,
            'balance' => $supplier->accountBalance(),
            'canManage' => $this->canManage(),
            'canViewPayments' => $this->canViewPayments(),
            'canManagePayments' => $this->canManagePayments(),
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        $this->abortUnlessCanManage();

        return Inertia::render('Admin/Suppliers/Form', [
            'supplier' => $this->supplierPayload($supplier),
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->abortUnlessCanManage();

        $supplier->update($this->validateSupplier($request));

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', 'تم تحديث بيانات المورد.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->abortUnlessCanManage();

        if ($supplier->purchases()->exists() || $supplier->payments()->exists()) {
            return redirect()
                ->route('admin.suppliers.show', $supplier)
                ->with('error', 'احذف فواتير المورد ودفعاته أولاً قبل حذف المورد نفسه.');
        }

        $supplier->delete();

        return redirect()
            ->route('admin.suppliers.index')
            ->with('success', 'تم حذف المورد.');
    }

    public function createPurchase(Supplier $supplier): Response
    {
        $this->abortUnlessCanManage();

        return Inertia::render('Admin/Suppliers/PurchaseForm', [
            'supplier' => $this->supplierPayload($supplier),
            'purchase' => null,
            ...$this->purchaseFormOptions(),
        ]);
    }

    public function storePurchase(Request $request, Supplier $supplier, SupplierPurchaseService $purchases): RedirectResponse
    {
        $this->abortUnlessCanManage();

        $data = $this->validatePurchase($request, false);
        $purchases->create(
            $supplier,
            $data,
            $data['lines'],
            $request->file('invoice'),
            (int) Auth::id()
        );

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', 'تم حفظ فاتورة المورد وتحديث المخزون.');
    }

    public function editPurchase(Supplier $supplier, SupplierPurchase $purchase): Response
    {
        $this->abortUnlessCanManage();
        $this->assertPurchaseBelongsToSupplier($supplier, $purchase);

        return Inertia::render('Admin/Suppliers/PurchaseForm', [
            'supplier' => $this->supplierPayload($supplier),
            'purchase' => $this->purchasePayload($purchase->load('lines.branch:id,name')),
            ...$this->purchaseFormOptions(),
        ]);
    }

    public function updatePurchase(Request $request, Supplier $supplier, SupplierPurchase $purchase, SupplierPurchaseService $purchases): RedirectResponse
    {
        $this->abortUnlessCanManage();
        $this->assertPurchaseBelongsToSupplier($supplier, $purchase);

        $data = $this->validatePurchase($request, false);
        $purchases->update($purchase, $data, $data['lines'], $request->file('invoice'));

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', 'تم تحديث الفاتورة والمخزون.');
    }

    public function destroyPurchase(Supplier $supplier, SupplierPurchase $purchase, SupplierPurchaseService $purchases): RedirectResponse
    {
        $this->abortUnlessCanManage();
        $this->assertPurchaseBelongsToSupplier($supplier, $purchase);

        $purchases->delete($purchase);

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', 'تم حذف الفاتورة وعكس أثرها على المخزون.');
    }

    public function invoice(Supplier $supplier, SupplierPurchase $purchase): StreamedResponse
    {
        $this->abortUnlessCanView();
        $this->assertPurchaseBelongsToSupplier($supplier, $purchase);

        $diskName = $purchase->invoice_disk ?: SupplierPurchaseService::invoiceDiskName();
        $disk = Storage::disk($diskName);
        abort_unless($purchase->invoice_path && $disk->exists($purchase->invoice_path), 404);

        return $disk->response(
            $purchase->invoice_path,
            $purchase->invoice_original_name ?: 'invoice',
            ['Content-Type' => $purchase->invoice_mime ?: 'application/octet-stream']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSupplier(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'required|boolean',
        ]);

        $data['contact_person'] = $this->blankToNull($data['contact_person'] ?? null);
        $data['phone'] = $this->blankToNull($data['phone'] ?? null);
        $data['email'] = $this->blankToNull($data['email'] ?? null);
        $data['notes'] = $this->blankToNull($data['notes'] ?? null);
        $data['is_active'] = (bool) $data['is_active'];

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePurchase(Request $request, bool $invoiceRequired): array
    {
        $data = $request->validate([
            'purchased_on' => 'required|date',
            'invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'invoice' => [$invoiceRequired ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
            'lines' => 'required|array|min:1',
            'lines.*.line_type' => 'required|in:raw,custom',
            'lines.*.product_id' => 'nullable|integer',
            'lines.*.custom_name' => 'nullable|string|max:255',
            'lines.*.custom_unit' => 'nullable|string|max:50',
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.stock_destination' => 'nullable|in:central,branch',
            'lines.*.branch_id' => 'nullable|integer',
        ]);

        foreach ($data['lines'] as $index => $line) {
            $label = 'البند '.($index + 1);
            if ($line['line_type'] === SupplierPurchaseLine::TYPE_RAW) {
                if (empty($line['product_id'])) {
                    throw ValidationException::withMessages([
                        "lines.$index.product_id" => "اختر مادة خاماً في {$label}.",
                    ]);
                }
                if (($line['stock_destination'] ?? '') === SupplierPurchaseLine::DEST_BRANCH && empty($line['branch_id'])) {
                    throw ValidationException::withMessages([
                        "lines.$index.branch_id" => "اختر الفرع في {$label}.",
                    ]);
                }
                if (! in_array($line['stock_destination'] ?? '', [SupplierPurchaseLine::DEST_CENTRAL, SupplierPurchaseLine::DEST_BRANCH], true)) {
                    throw ValidationException::withMessages([
                        "lines.$index.stock_destination" => "حدد أين تُضاف الكمية في {$label}.",
                    ]);
                }
            } elseif (trim((string) ($line['custom_name'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "lines.$index.custom_name" => "اكتب اسم البند في {$label}.",
                ]);
            }
        }

        $data['invoice_number'] = $this->blankToNull($data['invoice_number'] ?? null);
        $data['notes'] = $this->blankToNull($data['notes'] ?? null);

        return $data;
    }

    /**
     * @return array{categories: mixed, materials: mixed, branches: mixed}
     */
    private function purchaseFormOptions(): array
    {
        $categories = Category::forRawMaterials()->orderBy('name')->get(['id', 'name']);
        $materials = Product::query()
            ->where('type', 'raw')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'category_id',
                'purchase_unit',
                'consume_unit',
                'quantity_per_unit',
                'unit',
            ]);

        return [
            'categories' => $categories,
            'materials' => $materials,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierPayload(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact_person' => $supplier->contact_person,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'notes' => $supplier->notes,
            'is_active' => (bool) $supplier->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePayload(SupplierPurchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'purchased_on' => optional($purchase->purchased_on)->toDateString(),
            'invoice_number' => $purchase->invoice_number,
            'invoice_original_name' => $purchase->invoice_original_name,
            'invoice_mime' => $purchase->invoice_mime,
            'has_invoice' => (bool) $purchase->invoice_path && $purchase->invoice_path !== 'pending',
            'notes' => $purchase->notes,
            'total_amount' => round((float) $purchase->total_amount, 2),
            'lines' => $purchase->lines->map(fn (SupplierPurchaseLine $line) => [
                'id' => $line->id,
                'line_type' => $line->line_type,
                'product_id' => $line->product_id,
                'item_name' => $line->item_name,
                'unit' => $line->unit,
                'custom_name' => $line->line_type === SupplierPurchaseLine::TYPE_CUSTOM ? $line->item_name : '',
                'custom_unit' => $line->line_type === SupplierPurchaseLine::TYPE_CUSTOM ? $line->unit : '',
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->line_total,
                'stock_destination' => $line->stock_destination,
                'branch_id' => $line->branch_id,
                'branch_name' => $line->branch?->name,
                'stock_quantity_added' => (float) $line->stock_quantity_added,
            ])->values()->all(),
        ];
    }

    private function assertPurchaseBelongsToSupplier(Supplier $supplier, SupplierPurchase $purchase): void
    {
        abort_unless((int) $purchase->supplier_id === (int) $supplier->id, 404);
    }

    private function abortUnlessCanView(): void
    {
        $user = Auth::user();
        abort_unless(
            $user?->can('suppliers.view')
            || $user?->can('suppliers.manage')
            || $this->canViewPayments(),
            403
        );
    }

    private function canViewPayments(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->can('suppliers.payments.view') || $user?->can('suppliers.payments.manage'));
    }

    private function canManagePayments(): bool
    {
        return (bool) Auth::user()?->can('suppliers.payments.manage');
    }

    private function abortUnlessCanManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function canManage(): bool
    {
        return (bool) Auth::user()?->can('suppliers.manage');
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }
}
