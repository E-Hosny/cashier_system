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

            if ($request->hasFile('image')) {
            // حذف الصورة القديمة
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            // احتفظ بالصورة الحالية
            $data['image'] = $product->image;
        }


        $product->name = $data['name'];
        $product->price = $data['price'];
        $product->quantity = $data['quantity'] !== 'null' ? $data['quantity'] : null;
        $product->category_id = $data['category_id'];
        $product->image = $data['image'] ?? $product->image;
        $product->save();



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
