<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SalesReportController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\Admin\CategoryController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard', [
            'canViewReports' => Auth::user()->can('view sales reports'),
        ]);
    })->name('dashboard');

    // Products
    Route::get('products', [ProductController::class, 'index'])->name('admin.products.index');
    Route::post('products', [ProductController::class, 'store'])->name('admin.products.store');
    Route::put('/admin/products/update/{id}', [ProductController::class, 'update'])->name('admin.products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('admin.products.destroy');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Cashier & Invoices
    Route::get('/cashier', [CashierController::class, 'index'])->name('cashier.index');
    Route::post('/checkout', [CashierController::class, 'checkout'])->name('cashier.checkout');
    Route::get('/invoice/{orderId}', [CashierController::class, 'invoice'])->name('invoice.show');

    // Sales Report
    Route::get('/sales-report', [SalesReportController::class, 'index'])->name('admin.sales.report');

    // Purchases
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
});
