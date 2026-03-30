<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;

class BaristaController extends Controller
{
    public function index()
    {
        $products = Product::where('type', 'finished')
            ->latest()
            ->get()
            ->append('available_sizes');

        // Ensure size_variants is always an array to simplify frontend
        $products->transform(function ($product) {
            if (is_null($product->size_variants)) {
                $product->size_variants = [];
            }

            return $product;
        });

        // فقط فئات المنتجات النهائية المحددة للباريستا
        $categories = Category::forProducts()
            ->where('show_on_barista', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Barista', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }
}

