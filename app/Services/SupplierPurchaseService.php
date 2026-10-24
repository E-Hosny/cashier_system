<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchRawMaterialStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Models\SupplierPurchaseLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SupplierPurchaseService
{
    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(Supplier $supplier, array $header, array $lines, ?UploadedFile $invoice, int $userId): SupplierPurchase
    {
        return DB::transaction(function () use ($supplier, $header, $lines, $invoice, $userId) {
            $purchase = SupplierPurchase::create([
                'tenant_id' => $supplier->tenant_id,
                'supplier_id' => $supplier->id,
                'purchased_on' => $header['purchased_on'],
                'invoice_number' => $header['invoice_number'] ?? null,
                'invoice_path' => null,
                'notes' => $header['notes'] ?? null,
                'total_amount' => 0,
                'created_by' => $userId,
            ]);

            if ($invoice) {
                $stored = $this->storeInvoiceFile($invoice, "supplier-invoices/{$supplier->tenant_id}/{$purchase->id}");
                $purchase->update([
                    'invoice_path' => $stored['path'],
                    'invoice_disk' => $stored['disk'],
                    'invoice_original_name' => $invoice->getClientOriginalName(),
                    'invoice_mime' => $invoice->getClientMimeType(),
                ]);
            }

            $this->writeLines($purchase, $lines);

            return $purchase->fresh('lines');
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public function update(SupplierPurchase $purchase, array $header, array $lines, ?UploadedFile $invoice): SupplierPurchase
    {
        return DB::transaction(function () use ($purchase, $header, $lines, $invoice) {
            $this->reverseStock($purchase);
            $purchase->lines()->delete();

            if ($invoice) {
                $this->deleteInvoiceFile($purchase);
                $stored = $this->storeInvoiceFile($invoice, "supplier-invoices/{$purchase->tenant_id}/{$purchase->id}");
                $purchase->invoice_path = $stored['path'];
                $purchase->invoice_disk = $stored['disk'];
                $purchase->invoice_original_name = $invoice->getClientOriginalName();
                $purchase->invoice_mime = $invoice->getClientMimeType();
            }

            $purchase->fill([
                'purchased_on' => $header['purchased_on'],
                'invoice_number' => $header['invoice_number'] ?? null,
                'notes' => $header['notes'] ?? null,
            ]);
            $purchase->save();

            $this->writeLines($purchase, $lines);

            return $purchase->fresh('lines');
        });
    }

    public function delete(SupplierPurchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $purchase->load('lines');
            $this->reverseStock($purchase);
            $this->deleteInvoiceFile($purchase);
            $purchase->delete();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function writeLines(SupplierPurchase $purchase, array $lines): void
    {
        $total = 0.0;

        foreach ($lines as $line) {
            $built = $this->buildLine($purchase, $line);
            $total += $built['line_total'];
            $row = $purchase->lines()->create($built);
            $this->applyStock($row);
        }

        $purchase->update(['total_amount' => round($total, 2)]);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function buildLine(SupplierPurchase $purchase, array $line): array
    {
        $type = $line['line_type'];
        $quantity = round((float) $line['quantity'], 3);
        $unitPrice = round((float) $line['unit_price'], 2);
        $lineTotal = round($quantity * $unitPrice, 2);

        if ($type === SupplierPurchaseLine::TYPE_CUSTOM) {
            return [
                'tenant_id' => $purchase->tenant_id,
                'line_type' => SupplierPurchaseLine::TYPE_CUSTOM,
                'product_id' => null,
                'item_name' => trim((string) $line['custom_name']),
                'unit' => $this->blankToNull($line['custom_unit'] ?? null),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'stock_destination' => SupplierPurchaseLine::DEST_NONE,
                'branch_id' => null,
                'stock_quantity_added' => 0,
            ];
        }

        $product = Product::query()
            ->where('type', 'raw')
            ->whereKey((int) $line['product_id'])
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'lines' => 'إحدى المواد الخام غير موجودة.',
            ]);
        }

        $destination = $line['stock_destination'] ?? SupplierPurchaseLine::DEST_CENTRAL;
        $branchId = null;
        if ($destination === SupplierPurchaseLine::DEST_BRANCH) {
            $branchId = (int) ($line['branch_id'] ?? 0);
            $branchOk = Branch::query()
                ->whereKey($branchId)
                ->where('tenant_id', $purchase->tenant_id)
                ->exists();
            if (! $branchOk) {
                throw ValidationException::withMessages([
                    'lines' => 'اختر فرعاً صحيحاً للمادة '.$product->name.'.',
                ]);
            }
        } else {
            $destination = SupplierPurchaseLine::DEST_CENTRAL;
        }

        return [
            'tenant_id' => $purchase->tenant_id,
            'line_type' => SupplierPurchaseLine::TYPE_RAW,
            'product_id' => $product->id,
            'item_name' => $product->name,
            'unit' => $product->purchase_unit ?: $product->unit,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'stock_destination' => $destination,
            'branch_id' => $branchId,
            'stock_quantity_added' => round($this->stockUnits($product, $quantity), 4),
        ];
    }

    private function applyStock(SupplierPurchaseLine $line): void
    {
        $units = (float) $line->stock_quantity_added;
        if (! $line->isRaw() || $units <= 0 || ! $line->product_id) {
            return;
        }

        $this->moveStock($line, $units, 'supplier_purchase');
    }

    private function reverseStock(SupplierPurchase $purchase): void
    {
        foreach ($purchase->lines as $line) {
            $units = (float) $line->stock_quantity_added;
            if (! $line->isRaw() || $units <= 0 || ! $line->product_id) {
                continue;
            }
            if (! Product::query()->whereKey($line->product_id)->exists()) {
                continue;
            }
            $this->moveStock($line, -$units, 'supplier_purchase_reversal');
        }
    }

    private function moveStock(SupplierPurchaseLine $line, float $delta, string $type): void
    {
        $product = Product::query()->whereKey($line->product_id)->first();
        if (! $product) {
            return;
        }

        if ($line->stock_destination === SupplierPurchaseLine::DEST_BRANCH && $line->branch_id) {
            BranchRawMaterialStock::adjust((int) $line->branch_id, $product->id, $delta, $line->tenant_id);
            StockMovement::create([
                'product_id' => $product->id,
                'branch_id' => $line->branch_id,
                'quantity' => $delta,
                'type' => $type,
                'tenant_id' => $line->tenant_id,
            ]);

            return;
        }

        $product->increment('stock', $delta);
        StockMovement::create([
            'product_id' => $product->id,
            'quantity' => $delta,
            'type' => $type,
            'tenant_id' => $line->tenant_id,
        ]);
    }

    public function stockUnits(Product $product, float $quantity): float
    {
        if ($product->quantity_per_unit) {
            return $quantity * (float) $product->quantity_per_unit;
        }

        $purchaseUnit = $product->purchase_unit;
        $consumeUnit = $product->consume_unit;
        $factor = 1;

        if ($purchaseUnit && $consumeUnit) {
            $factors = [
                'لتر' => ['مللي' => 1000, 'لتر' => 1],
                'كجم' => ['جرام' => 1000, 'كجم' => 1],
                'قطعة' => ['قطعة' => 1],
            ];
            $factor = $factors[$purchaseUnit][$consumeUnit] ?? 1;
        }

        return $quantity * $factor;
    }

    public function deleteInvoiceFile(SupplierPurchase $purchase): void
    {
        if (! $purchase->invoice_path || $purchase->invoice_path === 'pending') {
            return;
        }

        $disk = Storage::disk($purchase->invoice_disk ?: self::invoiceDiskName());
        if ($disk->exists($purchase->invoice_path)) {
            $disk->delete($purchase->invoice_path);
        }
    }

    /**
     * Spaces عندما يكون مظبطاً، وإلا التخزين المحلي حتى يظهر الملف في التطوير.
     */
    public static function invoiceDiskName(): string
    {
        $bucket = config('filesystems.disks.spaces.bucket');

        return is_string($bucket) && $bucket !== '' ? SupplierPurchase::DISK : 'public';
    }

    /**
     * @return array{disk: string, path: string}
     */
    private function storeInvoiceFile(UploadedFile $invoice, string $directory): array
    {
        $diskName = self::invoiceDiskName();
        $path = $invoice->store($directory, $diskName);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'invoice' => 'تعذر حفظ ملف الفاتورة.',
            ]);
        }

        return ['disk' => $diskName, 'path' => $path];
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }
}
