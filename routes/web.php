<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SalesReportController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\RawMaterialController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\CashierShiftController;

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
    Route::resource('products', ProductController::class, ['names' => 'admin.products'])->except(['show']);
    Route::get('/products/export', [ProductController::class, 'export'])->name('admin.products.export');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->name('admin.categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('admin.categories.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');

    // Raw Materials
    Route::resource('raw-materials', RawMaterialController::class, ['as' => 'admin'])->except(['show']);

    // Users Management (admin only)
    Route::middleware(['admin'])->group(function () {
        Route::resource('users', UserController::class, ['as' => 'admin'])->except(['show']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
    });

    // Cashier & Invoices
    Route::get('/cashier', [CashierController::class, 'index'])->name('cashier.index');
    Route::post('/store-order', [CashierController::class, 'store'])->name('cashier.store');
    Route::get('/invoice/{orderId}', [CashierController::class, 'invoice'])->name('invoice.show');
    Route::get('/invoice-html/{orderId}', [CashierController::class, 'invoiceHtml']);

    // Sales Report
    Route::get('/sales-report', [SalesReportController::class, 'index'])->name('admin.sales.report');

    // Purchases
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');

    // Expenses
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Cashier Shifts
    Route::prefix('cashier-shifts')->group(function () {
        Route::post('/start', [CashierShiftController::class, 'startShift'])->name('cashier.shifts.start');
        Route::post('/close', [CashierShiftController::class, 'closeShift'])->name('cashier.shifts.close');
        Route::post('/handover', [CashierShiftController::class, 'handOverShift'])->name('cashier.shifts.handover');
        Route::put('/{shift}/update-cash', [CashierShiftController::class, 'updateCashAmount'])->name('cashier.shifts.update-cash');
        Route::get('/current', [CashierShiftController::class, 'getCurrentShift'])->name('cashier.shifts.current');
        Route::get('/details', [CashierShiftController::class, 'getShiftDetails'])->name('cashier.shifts.details');
        Route::get('/history', [CashierShiftController::class, 'getShiftHistory'])->name('cashier.shifts.history');
        Route::get('/stats', [CashierShiftController::class, 'getShiftStats'])->name('cashier.shifts.stats');
    });
});
