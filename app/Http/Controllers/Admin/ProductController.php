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
                    return [
                        $ingredient['raw_material_id'] => [
                            'quantity_consumed' => $ingredient['quantity_consumed'],
                            'size' => $variant['size']
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
                    return [
                        $ingredientId => [
                            'quantity_consumed' => $quantity,
                            'size' => $variant['size']
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
}
