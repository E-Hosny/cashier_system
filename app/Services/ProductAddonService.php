<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAddonGroup;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductAddonService
{
    /**
     * مجموعات الإضافات الإجبارية لمنتج (مع الخيارات والمنتجات).
     *
     * @return list<array<string, mixed>>
     */
    public function groupsForCashier(Product $product): array
    {
        $groups = $product->relationLoaded('addonGroups')
            ? $product->addonGroups
            : $product->addonGroups()
                ->with(['options.addonProduct:id,name,size_variants,category_id,is_draft,type'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        return $groups->map(function (ProductAddonGroup $group) {
            $options = $group->options
                ->filter(fn ($opt) => $opt->addonProduct
                    && $opt->addonProduct->type === 'finished'
                    && ! $opt->addonProduct->is_draft)
                ->map(function ($opt) {
                    $addon = $opt->addonProduct;
                    $variants = collect($addon->size_variants ?? [])
                        ->filter(fn ($v) => is_array($v) && isset($v['size']))
                        ->values()
                        ->map(fn ($v) => [
                            'size' => $v['size'],
                            'price' => (float) ($v['price'] ?? 0),
                        ])
                        ->all();

                    return [
                        'id' => $opt->id,
                        'addon_product_id' => $addon->id,
                        'name' => $addon->name,
                        'category_id' => $addon->category_id,
                        'size_variants' => $variants,
                        // السعر الإضافي فقط (0 = مشمول في سعر المنتج الأساسي)
                        'extra_price' => round((float) ($opt->extra_price ?? 0), 2),
                        'default_size' => $variants[0]['size'] ?? null,
                    ];
                })
                ->values()
                ->all();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'required_count' => (int) $group->required_count,
                'size' => $group->size,
                'options' => $options,
            ];
        })->filter(fn ($g) => count($g['options']) > 0)->values()->all();
    }

    /**
     * تحقق من بنود السلة ثم وسّع الإضافات إلى بنود طلب عادية.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: list<array<string, mixed>>, total: float}
     */
    public function resolveCheckoutItems(array $items): array
    {
        $parentIds = collect($items)
            ->filter(fn ($item) => empty($item['is_required_addon']) && empty($item['offer_id']))
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        $groupsByProduct = ProductAddonGroup::query()
            ->with(['options.addonProduct:id,name,size_variants,is_draft,type'])
            ->whereIn('product_id', $parentIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('product_id');

        $expanded = [];
        $bundlesSeen = [];

        foreach ($items as $item) {
            if (! empty($item['offer_id']) || ! empty($item['components'])) {
                $expanded[] = $item;
                continue;
            }

            if (! empty($item['is_required_addon'])) {
                // تُضاف مع الأب عبر الحزمة
                continue;
            }

            $productId = (int) $item['product_id'];
            $size = $item['size'] ?? null;
            $qty = (int) ($item['quantity'] ?? 0);
            $bundleKey = $item['required_addon_bundle_key'] ?? null;

            $applicable = collect($groupsByProduct->get($productId, []))
                ->filter(fn (ProductAddonGroup $g) => $g->appliesToSize($size))
                ->values();

            if ($applicable->isEmpty()) {
                $expanded[] = $this->normalizeLine($item);
                continue;
            }

            if (! $bundleKey) {
                throw ValidationException::withMessages([
                    'items' => 'المنتج «'.($item['product_name'] ?? $productId).'» يتطلب اختيار إضافات إجبارية.',
                ]);
            }

            if (isset($bundlesSeen[$bundleKey])) {
                throw ValidationException::withMessages([
                    'items' => 'حزمة إضافات مكررة في السلة.',
                ]);
            }
            $bundlesSeen[$bundleKey] = true;

            $addonLines = collect($items)->filter(
                fn ($row) => ! empty($row['is_required_addon'])
                    && ($row['required_addon_bundle_key'] ?? null) === $bundleKey
            )->values();

            foreach ($applicable as $group) {
                $this->assertGroupSatisfied($group, $addonLines, $qty, $item['product_name'] ?? '');
            }

            $expanded[] = $this->normalizeLine($item);

            foreach ($addonLines as $addon) {
                $option = $applicable
                    ->flatMap(fn (ProductAddonGroup $g) => $g->options)
                    ->first(function ($opt) use ($addon) {
                        return (int) $opt->addon_product_id === (int) $addon['product_id']
                            && (int) ($addon['required_addon_group_id'] ?? 0) === (int) $opt->group_id;
                    });

                if (! $option) {
                    throw ValidationException::withMessages([
                        'items' => 'إضافة غير مسموحة للمنتج الرئيسي.',
                    ]);
                }

                $expectedPrice = round((float) ($option->extra_price ?? 0), 2);
                if (abs((float) $addon['price'] - $expectedPrice) > 0.05) {
                    throw ValidationException::withMessages([
                        'items' => 'سعر إحدى الإضافات غير متطابق. حدّث الصفحة.',
                    ]);
                }

                $line = $this->normalizeLine($addon);
                $line['price'] = $expectedPrice;
                $expanded[] = $line;
            }
        }

        $total = collect($expanded)->sum(
            fn ($row) => ((float) ($row['price'] ?? 0)) * ((float) ($row['quantity'] ?? 0))
        );

        return [
            'items' => $expanded,
            'total' => round($total, 2),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $addonLines
     */
    private function assertGroupSatisfied(
        ProductAddonGroup $group,
        Collection $addonLines,
        int $parentQty,
        string $parentName,
    ): void {
        $optionIds = $group->options->pluck('addon_product_id')->map(fn ($id) => (int) $id)->all();
        $groupAddons = $addonLines->filter(
            fn ($row) => (int) ($row['required_addon_group_id'] ?? 0) === (int) $group->id
        );
        $selectedQty = (int) $groupAddons->sum(fn ($row) => (int) ($row['quantity'] ?? 0));

        // لكل وحدة من المنتج الرئيسي يجب اختيار required_count إضافات
        $needed = (int) $group->required_count * max(1, $parentQty);

        if ($selectedQty !== $needed) {
            throw ValidationException::withMessages([
                'items' => 'يجب اختيار '.$group->required_count.' من «'.$group->name.'» لكل وحدة من «'.$parentName.'» (المطلوب الإجمالي: '.$needed.').',
            ]);
        }

        foreach ($groupAddons as $row) {
            if (! in_array((int) $row['product_id'], $optionIds, true)) {
                throw ValidationException::withMessages([
                    'items' => 'اختيار غير صالح ضمن «'.$group->name.'».',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeLine(array $item): array
    {
        return [
            'product_id' => (int) $item['product_id'],
            'product_name' => (string) ($item['product_name'] ?? ''),
            'quantity' => (int) ($item['quantity'] ?? 0),
            'price' => (float) ($item['price'] ?? 0),
            'size' => $item['size'] ?? null,
            'from_fridge' => ! empty($item['from_fridge']),
            'offer_id' => $item['offer_id'] ?? null,
            'offer_bundle_key' => $item['offer_bundle_key'] ?? null,
            'original_unit_price' => $item['original_unit_price'] ?? null,
        ];
    }

    /**
     * مزامنة مجموعات الإضافات من نموذج تعديل المنتج.
     *
     * @param  array<int, array<string, mixed>>  $groups
     */
    public function syncGroups(Product $product, array $groups): void
    {
        $existingIds = $product->addonGroups()->pluck('id');
        if ($existingIds->isNotEmpty()) {
            \App\Models\ProductAddonOption::query()->whereIn('group_id', $existingIds)->delete();
            ProductAddonGroup::query()->whereIn('id', $existingIds)->delete();
        }

        foreach (array_values($groups) as $index => $groupData) {
            // يدعم options[{addon_product_id, extra_price}] أو addon_product_ids[] للتوافق
            $options = collect($groupData['options'] ?? []);
            if ($options->isEmpty() && ! empty($groupData['addon_product_ids'])) {
                $options = collect($groupData['addon_product_ids'])->map(fn ($id) => [
                    'addon_product_id' => (int) $id,
                    'extra_price' => 0,
                ]);
            }

            $options = $options
                ->map(fn ($row) => [
                    'addon_product_id' => (int) ($row['addon_product_id'] ?? $row['id'] ?? 0),
                    'extra_price' => max(0, round((float) ($row['extra_price'] ?? 0), 2)),
                ])
                ->filter(fn ($row) => $row['addon_product_id'] > 0)
                ->unique('addon_product_id')
                ->values();

            if ($options->isEmpty()) {
                continue;
            }

            $optionIds = $options->pluck('addon_product_id');
            $required = max(1, (int) ($groupData['required_count'] ?? 1));
            if ($required > $optionIds->count()) {
                throw ValidationException::withMessages([
                    'addon_groups' => 'عدد الاختيارات الإجبارية لا يمكن أن يتجاوز عدد المنتجات المتاحة في المجموعة.',
                ]);
            }

            if ($optionIds->contains((int) $product->id)) {
                throw ValidationException::withMessages([
                    'addon_groups' => 'لا يمكن ربط المنتج بنفسه كإضافة.',
                ]);
            }

            $invalid = Product::query()
                ->whereIn('id', $optionIds)
                ->where(function ($q) {
                    $q->where('type', '!=', 'finished')->orWhere('is_draft', true);
                })
                ->exists();

            if ($invalid) {
                throw ValidationException::withMessages([
                    'addon_groups' => 'الإضافات يجب أن تكون منتجات نهائية منشورة فقط.',
                ]);
            }

            $group = ProductAddonGroup::query()->create([
                'product_id' => $product->id,
                'name' => trim((string) ($groupData['name'] ?? '')) ?: 'إضافات إجبارية',
                'required_count' => $required,
                'size' => filled($groupData['size'] ?? null) ? $groupData['size'] : null,
                'sort_order' => $index,
            ]);

            foreach ($options as $optIndex => $opt) {
                $group->options()->create([
                    'addon_product_id' => $opt['addon_product_id'],
                    'extra_price' => $opt['extra_price'],
                    'sort_order' => $optIndex,
                ]);
            }
        }
    }
}
