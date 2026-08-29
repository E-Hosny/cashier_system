<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OfferMatchingService
{
    /**
     * @param  array<int, array<string, mixed>>  $offers  Cashier-format offers
     * @param  array<int, array<string, mixed>>  $cartItems  Regular cart lines (non-offer, non-fridge)
     * @return array{applied: array<int, array<string, mixed>>, remaining: array<int, array<string, mixed>>}
     */
    public function applyOffersToCart(array $offers, array $cartItems): array
    {
        $sortedOffers = collect($offers)->sortByDesc('priority')->values()->all();
        $units = $this->expandCartUnits($cartItems);
        $applied = [];

        foreach ($sortedOffers as $offer) {
            while (true) {
                $match = $this->tryMatchOffer($offer, $units);
                if ($match === null) {
                    break;
                }

                foreach ($match['used_keys'] as $key) {
                    unset($units[$key]);
                }

                $applied[] = $this->buildAppliedBundle($offer, $match['units']);
            }
        }

        $remaining = $this->collapseUnitsToCartLines($units, $cartItems);

        return [
            'applied' => $applied,
            'remaining' => $remaining,
        ];
    }

    /**
     * Validate checkout offer lines and expand to order item rows.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, total: float}
     */
    public function resolveCheckoutItems(array $items): array
    {
        $offers = Offer::query()
            ->active()
            ->with(['rules.products'])
            ->get()
            ->keyBy('id');

        $productIds = collect($items)->flatMap(function ($item) {
            if (! empty($item['offer_id'])) {
                return collect($item['components'] ?? [])->pluck('product_id');
            }

            return [$item['product_id'] ?? null];
        })->filter()->unique();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $expanded = [];
        $total = 0.0;

        foreach ($items as $item) {
            if (! empty($item['offer_id'])) {
                $bundle = $this->validateOfferLine($item, $offers, $products);
                $total += $bundle['line_total'];
                foreach ($bundle['order_items'] as $orderItem) {
                    $expanded[] = $orderItem;
                }

                continue;
            }

            $lineTotal = round((float) $item['price'] * (int) $item['quantity'], 2);
            $total += $lineTotal;
            $expanded[] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['price'],
                'size' => $item['size'] ?? null,
                'from_fridge' => ! empty($item['from_fridge']),
                'offer_id' => null,
                'offer_bundle_key' => null,
                'original_unit_price' => null,
            ];
        }

        return [
            'items' => $expanded,
            'total' => round($total, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<int, Offer>  $offers
     * @param  Collection<int, Product>  $products
     * @return array{line_total: float, order_items: array<int, array<string, mixed>>}
     */
    private function validateOfferLine(array $item, Collection $offers, Collection $products): array
    {
        $offerId = (int) $item['offer_id'];
        $offer = $offers->get($offerId);
        if (! $offer) {
            throw new InvalidArgumentException('عرض غير موجود أو غير نشط.');
        }

        $components = $item['components'] ?? [];
        if (! is_array($components) || $components === []) {
            throw new InvalidArgumentException('بيانات العرض غير مكتملة.');
        }

        $offerArray = $offer->toCashierArray();
        $units = [];
        foreach ($components as $idx => $component) {
            $productId = (int) ($component['product_id'] ?? 0);
            $qty = (int) ($component['quantity'] ?? 1);
            $product = $products->get($productId);
            if (! $product) {
                throw new InvalidArgumentException('منتج في العرض غير صالح.');
            }
            for ($i = 0; $i < $qty; $i++) {
                $units["c{$idx}-{$i}"] = [
                    'product_id' => $productId,
                    'category_id' => $product->category_id,
                    'size' => $component['size'] ?? null,
                    'name' => $component['product_name'] ?? $product->name,
                    'price' => (float) ($component['unit_price'] ?? 0),
                ];
            }
        }

        $match = $this->tryMatchOffer($offerArray, $units);
        if ($match === null) {
            throw new InvalidArgumentException('السلة لا تطابق شروط العرض: '.$offer->name);
        }

        $expectedPrice = round((float) $offer->offer_price, 2);
        $sentPrice = round((float) ($item['price'] ?? 0), 2);
        if (abs($expectedPrice - $sentPrice) > 0.02) {
            throw new InvalidArgumentException('سعر العرض غير صحيح.');
        }

        $bundleKey = (string) Str::uuid();
        $bundleQty = max(1, (int) ($item['quantity'] ?? 1));
        $lineTotal = round($expectedPrice * $bundleQty, 2);
        $componentGroups = collect($components)->map(function ($component) use ($bundleQty) {
            return [
                'product_id' => (int) $component['product_id'],
                'product_name' => $component['product_name'],
                'size' => $component['size'] ?? null,
                'quantity' => (int) ($component['quantity'] ?? 1) * $bundleQty,
                'unit_price' => (float) ($component['unit_price'] ?? 0),
            ];
        });

        $originalTotal = $componentGroups->sum(fn ($c) => $c['unit_price'] * $c['quantity']);
        $orderItems = [];

        foreach ($componentGroups as $component) {
            $share = $originalTotal > 0
                ? ($component['unit_price'] * $component['quantity']) / $originalTotal
                : 0;
            $allocatedLineTotal = round($lineTotal * $share, 2);
            $unitPrice = $component['quantity'] > 0
                ? round($allocatedLineTotal / $component['quantity'], 2)
                : 0;

            $orderItems[] = [
                'product_id' => $component['product_id'],
                'product_name' => $component['product_name'].' (عرض: '.$offer->name.')',
                'quantity' => $component['quantity'],
                'price' => $unitPrice,
                'size' => $component['size'],
                'from_fridge' => false,
                'offer_id' => $offerId,
                'offer_bundle_key' => $bundleKey,
                'original_unit_price' => round($component['unit_price'], 2),
            ];
        }

        return [
            'line_total' => $lineTotal,
            'order_items' => $orderItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, array<string, mixed>>  $units
     * @return array{units: array<int, array<string, mixed>>, used_keys: array<int, string>}|null
     */
    private function tryMatchOffer(array $offer, array $units): ?array
    {
        $usedKeys = [];
        $matchedUnits = [];
        $rules = collect($offer['rules'] ?? [])->sortBy('slot_index')->values();

        foreach ($rules as $rule) {
            $picked = $this->pickForRule($rule, $units, $usedKeys);
            if ($picked === null) {
                return null;
            }
            foreach ($picked as $key => $unit) {
                $usedKeys[] = $key;
                $matchedUnits[] = $unit;
            }
        }

        return [
            'units' => $matchedUnits,
            'used_keys' => $usedKeys,
        ];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, array<string, mixed>>  $units
     * @param  array<int, string>  $usedKeys
     * @return array<string, array<string, mixed>>|null
     */
    private function pickForRule(array $rule, array $units, array $usedKeys): ?array
    {
        $picked = [];
        $usedSet = array_flip($usedKeys);
        $ruleType = $rule['rule_type'] ?? '';

        if ($ruleType === Offer::RULE_FIXED_PRODUCTS) {
            foreach ($rule['products'] ?? [] as $ruleProduct) {
                $need = (int) ($ruleProduct['quantity'] ?? 1);
                for ($i = 0; $i < $need; $i++) {
                    $foundKey = null;
                    foreach ($units as $key => $unit) {
                        if (isset($usedSet[$key])) {
                            continue;
                        }
                        if ((int) $unit['product_id'] !== (int) $ruleProduct['product_id']) {
                            continue;
                        }
                        if (! empty($ruleProduct['size']) && ($unit['size'] ?? null) !== $ruleProduct['size']) {
                            continue;
                        }
                        $foundKey = $key;
                        break;
                    }
                    if ($foundKey === null) {
                        return null;
                    }
                    $picked[$foundKey] = $units[$foundKey];
                    $usedSet[$foundKey] = true;
                }
            }

            return $picked;
        }

        if ($ruleType === Offer::RULE_CATEGORY_PICK) {
            $need = (int) ($rule['quantity'] ?? 1);
            $categoryId = (int) ($rule['category_id'] ?? 0);
            for ($i = 0; $i < $need; $i++) {
                $foundKey = null;
                foreach ($units as $key => $unit) {
                    if (isset($usedSet[$key])) {
                        continue;
                    }
                    if ((int) ($unit['category_id'] ?? 0) === $categoryId) {
                        $foundKey = $key;
                        break;
                    }
                }
                if ($foundKey === null) {
                    return null;
                }
                $picked[$foundKey] = $units[$foundKey];
                $usedSet[$foundKey] = true;
            }

            return $picked;
        }

        if ($ruleType === Offer::RULE_PRODUCT_PICK) {
            $need = (int) ($rule['quantity'] ?? 1);
            $allowed = collect($rule['products'] ?? [])->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            for ($i = 0; $i < $need; $i++) {
                $foundKey = null;
                foreach ($units as $key => $unit) {
                    if (isset($usedSet[$key])) {
                        continue;
                    }
                    if (in_array((int) $unit['product_id'], $allowed, true)) {
                        $foundKey = $key;
                        break;
                    }
                }
                if ($foundKey === null) {
                    return null;
                }
                $picked[$foundKey] = $units[$foundKey];
                $usedSet[$foundKey] = true;
            }

            return $picked;
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @return array<string, array<string, mixed>>
     */
    private function expandCartUnits(array $cartItems): array
    {
        $units = [];
        foreach ($cartItems as $item) {
            if (! empty($item['from_fridge']) || ! empty($item['type']) && $item['type'] === 'offer') {
                continue;
            }
            $qty = (int) ($item['quantity'] ?? 1);
            for ($i = 0; $i < $qty; $i++) {
                $key = ($item['cartItemId'] ?? $item['product_id']).'#'.$i;
                $units[$key] = [
                    'cartItemId' => $item['cartItemId'] ?? null,
                    'product_id' => (int) $item['product_id'],
                    'category_id' => $item['category_id'] ?? null,
                    'size' => $item['size'] ?? null,
                    'name' => $item['name'] ?? $item['product_name'] ?? '',
                    'price' => (float) ($item['price'] ?? 0),
                ];
            }
        }

        return $units;
    }

    /**
     * @param  array<string, array<string, mixed>>  $units
     * @param  array<int, array<string, mixed>>  $originalCart
     * @return array<int, array<string, mixed>>
     */
    private function collapseUnitsToCartLines(array $units, array $originalCart): array
    {
        $counts = [];
        foreach ($units as $unit) {
            $cartItemId = $unit['cartItemId'] ?? ($unit['product_id'].'-'.($unit['size'] ?? ''));
            $counts[$cartItemId] = ($counts[$cartItemId] ?? 0) + 1;
        }

        $originalById = collect($originalCart)->keyBy(fn ($item) => $item['cartItemId'] ?? '');
        $remaining = [];

        foreach ($counts as $cartItemId => $qty) {
            $base = $originalById->get($cartItemId);
            if (! $base) {
                continue;
            }
            $remaining[] = [
                ...$base,
                'quantity' => $qty,
            ];
        }

        return array_values($remaining);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<int, array<string, mixed>>  $units
     * @return array<string, mixed>
     */
    private function buildAppliedBundle(array $offer, array $units): array
    {
        $grouped = [];
        foreach ($units as $unit) {
            $key = $unit['product_id'].'|'.($unit['size'] ?? '');
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'product_id' => $unit['product_id'],
                    'product_name' => $unit['name'],
                    'size' => $unit['size'] ?? null,
                    'quantity' => 0,
                    'unit_price' => $unit['price'],
                ];
            }
            $grouped[$key]['quantity']++;
        }

        $components = array_values($grouped);
        $originalTotal = array_sum(array_map(fn ($c) => $c['unit_price'] * $c['quantity'], $components));

        return [
            'cartItemId' => 'offer-'.$offer['id'].'-'.Str::uuid()->toString(),
            'type' => 'offer',
            'offer_id' => $offer['id'],
            'name' => $offer['name'],
            'price' => (float) $offer['offer_price'],
            'quantity' => 1,
            'original_total' => round($originalTotal, 2),
            'savings' => round(max(0, $originalTotal - (float) $offer['offer_price']), 2),
            'components' => $components,
        ];
    }
}
