<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPaymentController extends Controller
{
    public function index(Supplier $supplier): Response
    {
        $this->abortUnlessCanView();

        $payments = $supplier->payments()
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SupplierPayment $payment) => $this->payload($payment));

        return Inertia::render('Admin/Suppliers/Payments/Index', [
            'supplier' => ['id' => $supplier->id, 'name' => $supplier->name],
            'payments' => $payments,
            'balance' => $supplier->accountBalance(),
            'canManage' => $this->canManage(),
        ]);
    }

    public function create(Supplier $supplier): Response
    {
        $this->abortUnlessCanManage();

        $balance = $supplier->accountBalance();

        return Inertia::render('Admin/Suppliers/Payments/Form', [
            'supplier' => ['id' => $supplier->id, 'name' => $supplier->name],
            'payment' => null,
            'balance' => $balance,
            'available' => $balance['remaining'],
        ]);
    }

    public function store(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->abortUnlessCanManage();

        $data = $this->validatePayment($request, $supplier);

        SupplierPayment::create([
            ...$data,
            'supplier_id' => $supplier->id,
            'tenant_id' => $supplier->tenant_id,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.suppliers.payments.index', $supplier)
            ->with('success', 'تم تسجيل الدفعة.');
    }

    public function edit(Supplier $supplier, SupplierPayment $payment): Response
    {
        $this->abortUnlessCanManage();
        $this->assertBelongs($supplier, $payment);

        $balance = $supplier->accountBalance();

        return Inertia::render('Admin/Suppliers/Payments/Form', [
            'supplier' => ['id' => $supplier->id, 'name' => $supplier->name],
            'payment' => $this->payload($payment),
            'balance' => $balance,
            'available' => round($balance['remaining'] + (float) $payment->amount, 2),
        ]);
    }

    public function update(Request $request, Supplier $supplier, SupplierPayment $payment): RedirectResponse
    {
        $this->abortUnlessCanManage();
        $this->assertBelongs($supplier, $payment);

        $payment->update($this->validatePayment($request, $supplier, $payment));

        return redirect()
            ->route('admin.suppliers.payments.index', $supplier)
            ->with('success', 'تم تحديث الدفعة.');
    }

    public function destroy(Supplier $supplier, SupplierPayment $payment): RedirectResponse
    {
        $this->abortUnlessCanManage();
        $this->assertBelongs($supplier, $payment);

        $payment->delete();

        return redirect()
            ->route('admin.suppliers.payments.index', $supplier)
            ->with('success', 'تم حذف الدفعة.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayment(Request $request, Supplier $supplier, ?SupplierPayment $current = null): array
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'paid_on' => 'required|date',
            'method' => 'nullable|in:cash,transfer,other',
            'notes' => 'nullable|string|max:2000',
        ]);

        $balance = $supplier->accountBalance();
        $available = $balance['remaining'];
        if ($current) {
            $available = round($available + (float) $current->amount, 2);
        }

        if ((float) $data['amount'] - $available > 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'المبلغ أكبر من المتبقي ('.number_format($available, 2).').',
            ]);
        }

        $data['amount'] = round((float) $data['amount'], 2);
        $data['method'] = $data['method'] ?: null;
        $data['notes'] = isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SupplierPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'paid_on' => optional($payment->paid_on)->toDateString(),
            'method' => $payment->method,
            'notes' => $payment->notes,
        ];
    }

    private function assertBelongs(Supplier $supplier, SupplierPayment $payment): void
    {
        abort_unless((int) $payment->supplier_id === (int) $supplier->id, 404);
    }

    private function abortUnlessCanView(): void
    {
        abort_unless($this->canView(), 403);
    }

    private function abortUnlessCanManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function canView(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->can('suppliers.payments.view') || $user?->can('suppliers.payments.manage'));
    }

    private function canManage(): bool
    {
        return (bool) Auth::user()?->can('suppliers.payments.manage');
    }
}
