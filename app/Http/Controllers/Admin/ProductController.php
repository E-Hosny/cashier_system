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
        $products = Product::with('category')->latest()->get();
        $categories = Category::latest()->get();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'quantity' => 'nullable|integer',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        Product::create([
            'name' => $request->name,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'category_id' => $request->category_id,
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.products.index')->with('success', 'تمت إضافة المنتج بنجاح!');
    }

public function update(Request $request, $id)
{
    \Log::info('✅ دخلنا دالة التحديث', ['id' => $id, 'data' => $request->all()]);
    \Log::info('📷 هل تم رفع صورة؟', ['hasFile' => $request->hasFile('image')]);

    $product = Product::findOrFail($id);

    $data = $request->validate([
        'name' => 'required|string|max:255',
        'price' => 'required|numeric',
        'quantity' => 'nullable|integer',
        'category_id' => 'required|exists:categories,id',
        'image' => 'nullable|image|max:2048',
    ]);

    // معالجة الصورة الجديدة لو تم رفعها
    if ($request->hasFile('image')) {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $data['image'] = $request->file('image')->store('products', 'public');
    } else {
        $data['image'] = $product->image; // الاحتفاظ بالصورة القديمة
    }

    // معالجة الكمية: لو جاية كـ "null" نص → نحولها فعليًا لـ null
    $data['quantity'] = $data['quantity'] !== 'null' ? $data['quantity'] : null;

    // تنفيذ التحديث
    $product->forceFill($data)->save();

    \Log::info('📝 بعد الحفظ، بيانات المنتج:', $product->toArray());

    return redirect()->back()->with('success', 'تم تحديث المنتج بنجاح!');
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
