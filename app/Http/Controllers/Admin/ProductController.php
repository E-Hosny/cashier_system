<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->latest()->get()->append(['sizes_in_arabic', 'available_sizes']);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
        ]);
    }

    public function create()
    {
        $categories = Category::latest()->get();
        $sizes = [
            ['value' => 'small', 'label' => 'صغير'],
            ['value' => 'medium', 'label' => 'وسط'],
            ['value' => 'large', 'label' => 'كبير'],
        ];

        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories,
            'sizes' => $sizes,
        ]);
    }

    public function edit(Product $product)
    {
        $categories = Category::latest()->get();
        $sizes = [
            ['value' => 'small', 'label' => 'صغير'],
            ['value' => 'medium', 'label' => 'وسط'],
            ['value' => 'large', 'label' => 'كبير'],
        ];

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product->append(['available_sizes']),
            'categories' => $categories,
            'sizes' => $sizes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'quantity' => 'nullable|integer',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'size_variants' => 'required|array|min:1',
            'size_variants.*.size' => 'required|string',
            'size_variants.*.price' => 'required|numeric|min:0',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }
        
        $data['category_id'] = $data['category_id'] === 'null' ? null : $data['category_id'];

        Product::create($data);

        return redirect()->route('admin.products.index')->with('success', 'تمت إضافة المنتج بنجاح!');
    }

    public function update(Request $request, $id)
    {
        \Log::info('✅ دخلنا دالة التحديث', ['id' => $id, 'data' => $request->all()]);
        \Log::info('📷 هل تم رفع صورة؟', ['hasFile' => $request->hasFile('image')]);

        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'quantity' => 'nullable|integer',
            'category_id' => 'nullable|exists:categories,id',
            'size_variants' => 'required|array|min:1',
            'size_variants.*.size' => 'required|string',
            'size_variants.*.price' => 'required|numeric|min:0',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            $data['image'] = $product->image;
        }

        $data['quantity'] = $request->input('quantity') === 'null' ? null : $request->input('quantity');
        $data['category_id'] = $request->input('category_id') === 'null' ? null : $request->input('category_id');
        
        $product->forceFill($data)->save();

        \Log::info('📝 بعد الحفظ، بيانات المنتج:', $product->toArray());

        return redirect()->route('admin.products.index')->with('success', 'تم تحديث المنتج بنجاح!');
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
