<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Offer;
use App\Models\OfferRule;
use App\Models\OfferRuleProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OfferController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->hasRole('super admin'), 403);

        $offers = Offer::query()
            ->withCount('rules')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Offer $offer) => [
                'id' => $offer->id,
                'name' => $offer->name,
                'description' => $offer->description,
                'offer_price' => (float) $offer->offer_price,
                'is_active' => $offer->is_active,
                'priority' => (int) $offer->priority,
                'starts_at' => optional($offer->starts_at)?->format('Y-m-d\TH:i'),
                'ends_at' => optional($offer->ends_at)?->format('Y-m-d\TH:i'),
                'rules_count' => $offer->rules_count,
            ]);

        return Inertia::render('Admin/Offers/Index', [
            'offers' => $offers,
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()?->hasRole('super admin'), 403);

        return Inertia::render('Admin/Offers/Form', [
            'offer' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()?->hasRole('super admin'), 403);

        $data = $this->validateOffer($request);

        DB::transaction(function () use ($data) {
            $offer = Offer::create($data['offer']);
            $this->syncRules($offer, $data['rules']);
        });

        return redirect()->route('admin.offers.index')->with('success', 'تم إنشاء العرض بنجاح');
    }

    public function edit(Offer $offer)
    {
        abort_unless(auth()->user()?->hasRole('super admin'), 403);

        $offer->load(['rules.products.product:id,name']);

        return Inertia::render('Admin/Offers/Form', [
            'offer' => [
                'id' => $offer->id,
                'name' => $offer->name,
                'description' => $offer->description,
                'offer_price' => (float) $offer->offer_price,
                'is_active' => $offer->is_active,
                'priority' => (int) $offer->priority,
                'starts_at' => optional($offer->starts_at)?->format('Y-m-d\TH:i'),
                'ends_at' => optional($offer->ends_at)?->format('Y-m-d\TH:i'),
                'rules' => $offer->rules->map(fn (OfferRule $rule) => [
                    'slot_index' => $rule->slot_index,
                    'rule_type' => $rule->rule_type,
                    'quantity' => (int) $rule->quantity,
                    'category_id' => $rule->category_id,
                    'size' => $rule->size,
                    'products' => $rule->products->map(fn ($rp) => [
                        'product_id' => $rp->product_id,
                        'product_name' => optional($rp->product)->name,
                        'quantity' => (int) $rp->quantity,
                        'size' => $rp->size,
                    ])->values()->all(),
                ])->values()->all(),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Offer $offer)
    {
        abort_unless(auth()->user()?->hasRole('super admin'), 403);

        $data = $this->validateOffer($request);

        DB::transaction(function () use ($offer, $data) {
            $offer->update($data['offer']);
            $offer->rules()->each(function (OfferRule $rule) {
                $rule->products()->delete();
                $rule->delete();
            });
            $this->syncRules($offer, $data['rules']);
        });

        return redirect()->route('admin.offers.index')->with('success', 'تم تحديث العرض بنجاح');
    }

    public function destroy(Offer $offer)
    {
        abort_unless(auth()->user()?->hasRole('super admin'), 403);

        $offer->delete();

        return redirect()->route('admin.offers.index')->with('success', 'تم حذف العرض');
    }

    public function toggle(Offer $offer)
    {
        abort_unless(auth()->user()?->hasRole('super admin'), 403);

        $offer->update(['is_active' => ! $offer->is_active]);

        return back()->with('success', $offer->is_active ? 'تم تفعيل العرض' : 'تم إيقاف العرض');
    }

    private function formOptions(): array
    {
        return [
            'categories' => Category::forProducts()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()
                ->where('type', 'finished')
                ->published()
                ->orderBy('name')
                ->get(['id', 'name', 'category_id', 'size_variants']),
        ];
    }

    private function validateOffer(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'offer_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:999',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'rules' => 'required|array|min:1',
            'rules.*.rule_type' => 'required|in:fixed_products,category_pick,product_pick',
            'rules.*.quantity' => 'required|integer|min:1|max:50',
            'rules.*.category_id' => 'nullable|integer|exists:categories,id',
            'rules.*.size' => 'nullable|string|max:64',
            'rules.*.products' => 'nullable|array',
            'rules.*.products.*.product_id' => 'required|integer|exists:products,id',
            'rules.*.products.*.quantity' => 'nullable|integer|min:1|max:50',
            'rules.*.products.*.size' => 'nullable|string|max:64',
        ]);

        foreach ($validated['rules'] as $index => $rule) {
            if ($rule['rule_type'] === Offer::RULE_CATEGORY_PICK && empty($rule['category_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "rules.{$index}.category_id" => 'يجب اختيار فئة لهذا الشرط.',
                ]);
            }
            if ($rule['rule_type'] === Offer::RULE_CATEGORY_PICK && ! empty($rule['category_id'])) {
                $categorySizes = $this->sizesForCategory((int) $rule['category_id']);
                if (! empty($categorySizes) && empty($rule['size'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "rules.{$index}.size" => 'يجب اختيار حجم لهذا الشرط لأن منتجات الفئة لها أحجام.',
                    ]);
                }
                if (! empty($rule['size']) && ! empty($categorySizes) && ! in_array($rule['size'], $categorySizes, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "rules.{$index}.size" => 'الحجم المحدد غير متاح لمنتجات هذه الفئة.',
                    ]);
                }
            }
            if (in_array($rule['rule_type'], [Offer::RULE_FIXED_PRODUCTS, Offer::RULE_PRODUCT_PICK], true)
                && empty($rule['products'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "rules.{$index}.products" => 'يجب اختيار منتج واحد على الأقل.',
                ]);
            }

            if (in_array($rule['rule_type'], [Offer::RULE_FIXED_PRODUCTS, Offer::RULE_PRODUCT_PICK], true)) {
                foreach ($rule['products'] ?? [] as $productIndex => $productRow) {
                    $product = Product::query()->find($productRow['product_id'] ?? null);
                    if (! $product) {
                        continue;
                    }
                    $availableSizes = $product->available_sizes;
                    if (! empty($availableSizes) && empty($productRow['size'])) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "rules.{$index}.products.{$productIndex}.size" => 'يجب اختيار حجم للمنتج «'.$product->name.'».',
                        ]);
                    }
                    if (! empty($productRow['size']) && ! empty($availableSizes) && ! in_array($productRow['size'], $availableSizes, true)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "rules.{$index}.products.{$productIndex}.size" => 'الحجم المحدد غير متاح لهذا المنتج.',
                        ]);
                    }
                }
            }
        }

        return [
            'offer' => [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'offer_price' => $validated['offer_price'],
                'is_active' => $validated['is_active'] ?? true,
                'priority' => $validated['priority'] ?? 0,
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
            ],
            'rules' => $validated['rules'],
        ];
    }

    private function syncRules(Offer $offer, array $rules): void
    {
        foreach ($rules as $index => $ruleData) {
            $rule = $offer->rules()->create([
                'slot_index' => $index,
                'rule_type' => $ruleData['rule_type'],
                'quantity' => $ruleData['rule_type'] === Offer::RULE_FIXED_PRODUCTS
                    ? collect($ruleData['products'] ?? [])->sum('quantity')
                    : (int) $ruleData['quantity'],
                'category_id' => $ruleData['rule_type'] === Offer::RULE_CATEGORY_PICK
                    ? ($ruleData['category_id'] ?? null)
                    : null,
                'size' => $ruleData['rule_type'] === Offer::RULE_CATEGORY_PICK
                    ? ($this->normalizeSize($ruleData['size'] ?? null))
                    : null,
            ]);

            if (in_array($ruleData['rule_type'], [Offer::RULE_FIXED_PRODUCTS, Offer::RULE_PRODUCT_PICK], true)) {
                foreach ($ruleData['products'] ?? [] as $productRow) {
                    OfferRuleProduct::create([
                        'offer_rule_id' => $rule->id,
                        'product_id' => $productRow['product_id'],
                        'quantity' => $ruleData['rule_type'] === Offer::RULE_PRODUCT_PICK
                            ? 1
                            : (int) ($productRow['quantity'] ?? 1),
                        'size' => $this->normalizeSize($productRow['size'] ?? null),
                    ]);
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function sizesForCategory(int $categoryId): array
    {
        return Product::query()
            ->where('type', 'finished')
            ->where('category_id', $categoryId)
            ->get(['size_variants'])
            ->flatMap(fn (Product $product) => $product->available_sizes)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeSize(mixed $size): ?string
    {
        if ($size === null || $size === '' || $size === false) {
            return null;
        }

        return (string) $size;
    }
}
