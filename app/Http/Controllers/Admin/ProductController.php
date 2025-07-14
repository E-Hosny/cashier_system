<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Exports\ProductsExport;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'ingredients'])
            ->where('type', 'finished');

        // تصفية حسب الفئة إذا تم تحديدها
        if ($request->has('category_id') && $request->category_id !== '') {
            $query->where('category_id', $request->category_id);
        }

        // تصفية حسب الاسم إذا تم تحديده
        if ($request->has('searchTerm') && trim($request->searchTerm) !== '') {
            $search = $request->searchTerm;
            $query->where('name', 'like', "%$search%");
        }

        $products = $query->latest()->get()->append(['sizes_in_arabic', 'available_sizes']);
        $categories = Category::latest()->get();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'category_id' => $request->category_id ?? '',
                'searchTerm' => $request->searchTerm ?? '',
            ],
        ]);
    }

    public function export()
    {
        $filename = 'products_' . date('Y-m-d_H-i-s') . '.xlsx';
        return Excel::download(new ProductsExport, $filename);
    }

    public function create()
    {
        $categories = Category::latest()->get();
        $rawMaterials = Product::where('type', 'raw')->get(['id', 'name', 'unit']);
        $sizes = [
            ['value' => 'small', 'label' => 'صغير'],
            ['value' => 'medium', 'label' => 'وسط'],
            ['value' => 'large', 'label' => 'كبير'],
        ];

        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories,
            'sizes' => $sizes,
            'rawMaterials' => $rawMaterials,
        ]);
    }

    public function edit(Product $product)
    {
        $categories = Category::latest()->get();
        $rawMaterials = Product::where('type', 'raw')->get(['id', 'name', 'unit']);
        $sizes = [
            ['value' => 'small', 'label' => 'صغير'],
            ['value' => 'medium', 'label' => 'وسط'],
            ['value' => 'large', 'label' => 'كبير'],
        ];

        // Group ingredients by size for the form
        $ingredients_by_size = $product->ingredients->groupBy('pivot.size');

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product->append(['available_sizes']),
            'categories' => $categories,
            'sizes' => $sizes,
            'rawMaterials' => $rawMaterials,
            'ingredients_by_size' => $ingredients_by_size,
        ]);
    }

    public function store(Request $request)
    {
        // Validation logic needs to be updated to handle nested ingredients per size
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'size_variants' => 'required|array|min:1',
            'size_variants.*.size' => 'required|string',
            'size_variants.*.price' => 'required|numeric|min:0',
            'size_variants.*.ingredients' => 'nullable|array',
            'size_variants.*.ingredients.*.raw_material_id' => 'required|exists:products,id',
            'size_variants.*.ingredients.*.quantity_consumed' => 'required|numeric|min:0.001',
        ]);
        
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }
        $data['type'] = 'finished';
        $product = Product::create($data);

        // Save ingredients for each size variant
        foreach ($request->size_variants as $variant) {
            if (!empty($variant['ingredients'])) {
                $syncData = collect($variant['ingredients'])->mapWithKeys(function ($ingredient) use ($variant) {
                    $unit = $ingredient['unit'] ?? null;
                    return [
                        $ingredient['raw_material_id'] => [
                            'quantity_consumed' => $ingredient['quantity_consumed'],
                            'size' => $variant['size'],
                            'unit' => $unit
                        ]
                    ];
                });
                // Use attach instead of sync to add ingredients for each size without overriding others
                $product->ingredients()->attach($syncData);
            }
        }

        return redirect()->route('admin.products.index')->with('success', 'تمت إضافة المنتج بنجاح!');
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'size_variants' => 'required|array|min:1',
            'size_variants.*.size' => 'required|string',
            'size_variants.*.price' => 'required|numeric|min:0',
            'size_variants.*.ingredients' => 'nullable|array',
            'size_variants.*.ingredients.*.id' => 'required|exists:products,id',
            'size_variants.*.ingredients.*.quantity' => 'required|numeric|min:0.001',
        ]);
        
        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        // Detach all old ingredients first
        $product->ingredients()->detach();

        // Re-attach new ingredients for each size variant
        foreach ($request->size_variants as $variant) {
            if (!empty($variant['ingredients'])) {
                $syncData = collect($variant['ingredients'])->mapWithKeys(function ($ingredient) use ($variant) {
                    $ingredientId = $ingredient['id'] ?? $ingredient['raw_material_id'];
                    $quantity = $ingredient['quantity'] ?? $ingredient['quantity_consumed'];
                    $unit = $ingredient['unit'] ?? null;
                    return [
                        $ingredientId => [
                            'quantity_consumed' => $quantity,
                            'size' => $variant['size'],
                            'unit' => $unit
                        ]
                    ];
                });
                $product->ingredients()->attach($syncData);
            }
        }

        return redirect()->route('admin.products.index')->with('success', 'تم تحديث المنتج بنجاح!');
    }

    private function mapIngredients($ingredients)
    {
       // This function is no longer needed in this new structure
       // and can be removed or left unused.
    }

    public function destroy(Product $product)
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'تم حذف المنتج بنجاح!');
    }

    public function costAnalysis()
    {
        $products = Product::where('type', 'finished')
            ->with(['ingredients' => function ($query) {
                $query->select('products.id', 'products.name', 'products.unit', 'products.purchase_unit', 'products.purchase_quantity', 'products.purchase_price', 'products.consume_unit', 'products.unit_consume_price');
            }])
            ->get();

        // تأكد أن كل منتج لديه size_variants كمصفوفة وصفِّ فقط العناصر المعرفة والتي تحتوي على size
        foreach ($products as $product) {
            if (!is_array($product->size_variants)) {
                $product->size_variants = [];
            }
            $product->size_variants = collect($product->size_variants)
                ->filter(function($v) {
                    return is_array($v) && isset($v['size']);
                })
                ->values()
                ->toArray();
        }

        // حساب أسعار الوحدات للمكونات
        foreach ($products as $product) {
            foreach ($product->ingredients as $ingredient) {
                $ingredient->unit_price = $ingredient->getUnitPrice($ingredient->unit);
                if (!$ingredient->unit_price || $ingredient->unit_price == 0) {
                    $ingredient->unit_price_warning = '⚠️ لم يتم تحديد سعر وحدة الاستهلاك';
                }
            }
        }

        // إضافة بيانات تحليل التكلفة لكل حجم
        foreach ($products as $product) {
            $sizeVariants = $product->size_variants;
            foreach ($sizeVariants as $i => $variant) {
                $size = $variant['size'];
                // تكلفة المكونات لهذا الحجم
                $ingredients_cost = $product->calculateIngredientsCost($size);
                // هامش الربح بالريال
                $profit_amount = $product->getProfitAmount($size);
                // نسبة الربح بالمئة
                $profit_margin = $product->getProfitMargin($size);
                // أضفها للـ size_variant
                $sizeVariants[$i]['ingredients_cost'] = round($ingredients_cost, 2);
                $sizeVariants[$i]['profit_amount'] = round($profit_amount, 2);
                $sizeVariants[$i]['profit_margin'] = round($profit_margin, 2);
            }
            $product->size_variants = $sizeVariants;
        }

        return Inertia::render('Admin/Products/CostAnalysis', [
            'products' => $products,
        ]);
    }
}
