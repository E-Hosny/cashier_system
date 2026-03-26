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
use App\Http\Controllers\Admin\RawMaterialCategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\ExpenseController;

use App\Http\Controllers\CashierShiftController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\DisplayScreenController;

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
            'canManageAttendance' => Auth::user()->can('manage employee attendance'),
            'canManageFeedback' => Auth::user()->hasRole('admin') || Auth::user()->hasRole('super admin'),
        ]);
    })->name('dashboard');

    // Products
    Route::resource('products', ProductController::class, ['names' => 'admin.products'])->except(['show']);
    Route::get('/products/export', [ProductController::class, 'export'])->name('admin.products.export');
    Route::get('/products/cost-analysis', [ProductController::class, 'costAnalysis'])->name('admin.products.cost-analysis');
    Route::get('/products/sales-analysis', [ProductController::class, 'salesAnalysis'])->name('admin.products.sales-analysis')->middleware('super_admin');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->name('admin.categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('admin.categories.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');

    // Raw Materials (specific routes before resource)
    Route::get('/raw-materials/pending-receive', [RawMaterialController::class, 'receiveByBarcodeForm'])->name('admin.raw-materials.pending-receive');
    Route::post('/raw-materials/pending-receive', [RawMaterialController::class, 'receiveByBarcode'])->name('admin.raw-materials.pending-receive.store');
    Route::get('/raw-materials/labels/{label}/print', [RawMaterialController::class, 'printLabel'])->name('admin.raw-materials.labels.print');
    Route::post('/raw-materials/{raw_material}/labels', [RawMaterialController::class, 'storeLabel'])->name('admin.raw-materials.labels.store');
    Route::resource('raw-materials', RawMaterialController::class, ['as' => 'admin'])->except(['show']);
    Route::get('raw-materials/{raw_material}/add-quantity', [RawMaterialController::class, 'addQuantityForm'])->name('admin.raw-materials.add-quantity');
    Route::post('raw-materials/{raw_material}/add-quantity', [RawMaterialController::class, 'addQuantity'])->name('admin.raw-materials.add-quantity.store');

    // Users Management (super admin only)
    Route::middleware(['super_admin'])->group(function () {
        Route::get('/raw-material-categories', [RawMaterialCategoryController::class, 'index'])->name('admin.raw-material-categories.index');
        Route::post('/raw-material-categories', [RawMaterialCategoryController::class, 'store'])->name('admin.raw-material-categories.store');
        Route::put('/raw-material-categories/{category}', [RawMaterialCategoryController::class, 'update'])->name('admin.raw-material-categories.update');
        Route::delete('/raw-material-categories/{category}', [RawMaterialCategoryController::class, 'destroy'])->name('admin.raw-material-categories.destroy');

        Route::resource('users', UserController::class, ['as' => 'admin'])->except(['show']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
    });

    // Display Screen (super admin only)
    Route::middleware(['super_admin'])->prefix('admin/display-screen')->name('admin.display-screen.')->group(function () {
        Route::get('/', [DisplayScreenController::class, 'index'])->name('index');
        Route::post('/slides', [DisplayScreenController::class, 'storeSlide'])->name('slides.store');
        Route::put('/slides/order', [DisplayScreenController::class, 'updateOrder'])->name('slides.order');
        Route::put('/slides/{slide}', [DisplayScreenController::class, 'updateSlide'])->name('slides.update');
        Route::delete('/slides/{slide}', [DisplayScreenController::class, 'destroySlide'])->name('slides.destroy');
    });

    // Employees Management (admin and cashier with attendance permission)
    Route::middleware(['employee.attendance'])->group(function () {
        Route::resource('employees', EmployeeController::class, ['as' => 'admin'])->except(['show']);
        Route::post('/employees/{employee}/checkin', [EmployeeController::class, 'checkin'])->name('admin.employees.checkin');
        Route::post('/employees/{employee}/checkout', [EmployeeController::class, 'checkout'])->name('admin.employees.checkout');
        Route::get('/employees/{employee}/report', [EmployeeController::class, 'report'])->name('admin.employees.report');
        Route::get('/employees/salary-calculator', [EmployeeController::class, 'salaryCalculator'])->name('admin.employees.salary-calculator');
        Route::post('/employees/{employee}/calculate-salary', [EmployeeController::class, 'calculateSalary'])->name('admin.employees.calculate-salary');
        Route::post('/employees/{employee}/deliver-salary', [EmployeeController::class, 'deliverSalary'])->name('admin.employees.deliver-salary');
        Route::post('/employees/{employee}/undo-salary-delivery', [EmployeeController::class, 'undoSalaryDelivery'])->name('admin.employees.undo-salary-delivery');
        Route::post('/employees/{employee}/deliver-salary-for-date', [EmployeeController::class, 'deliverSalaryForDate'])->name('admin.employees.deliver-salary-for-date');
        Route::post('/employees/{employee}/deliver-salary-for-period', [EmployeeController::class, 'deliverSalaryForPeriod'])->name('admin.employees.deliver-salary-for-period');
        Route::post('/employees/{employee}/undo-salary-delivery-for-date', [EmployeeController::class, 'undoSalaryDeliveryForDate'])->name('admin.employees.undo-salary-delivery-for-date');
        Route::post('/employees/{employee}/add-discount', [EmployeeController::class, 'addDiscount'])->name('admin.employees.add-discount');
    });

    // Cashier & Invoices
    Route::get('/cashier', [CashierController::class, 'index'])->name('cashier.index');
    Route::post('/store-order', [CashierController::class, 'store'])->name('cashier.store');
    Route::get('/invoice/{orderId}', [CashierController::class, 'invoice'])->name('invoice.show');
    Route::get('/invoice-html/{orderId}', [CashierController::class, 'invoiceHtml']);
    Route::get('/invoices', [CashierController::class, 'invoicesToday'])->name('invoices.today');



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

    // Feedback Management (Admin only)
    Route::middleware(['admin'])->group(function () {
        Route::get('/admin/feedback', [AdminFeedbackController::class, 'index'])->name('admin.feedback.index');
        Route::get('/admin/feedback/create', [AdminFeedbackController::class, 'create'])->name('admin.feedback.create');
        Route::post('/admin/feedback', [AdminFeedbackController::class, 'store'])->name('admin.feedback.store');
        Route::get('/admin/feedback/{feedback}/edit', [AdminFeedbackController::class, 'edit'])->name('admin.feedback.edit');
        Route::put('/admin/feedback/{feedback}', [AdminFeedbackController::class, 'update'])->name('admin.feedback.update');
        Route::delete('/admin/feedback/{feedback}', [AdminFeedbackController::class, 'destroy'])->name('admin.feedback.destroy');
        Route::put('/admin/feedback/{id}/approve', [AdminFeedbackController::class, 'approve'])->name('admin.feedback.approve');
        Route::post('/admin/feedback/bulk-action', [AdminFeedbackController::class, 'bulkAction'])->name('admin.feedback.bulk-action');
    });
});

// Public Feedback Routes (No Authentication) — كل رابط عام حسب الـ tenant (يجب تعريف display قبل form لتجنب تفسير "display" كـ tenant)
Route::get('/feedback/display/{tenant?}', [FeedbackController::class, 'publicDisplay'])->name('feedback.public.display');
Route::get('/feedback/{tenant?}', [FeedbackController::class, 'publicForm'])->name('feedback.public.form');
Route::post('/feedback', [FeedbackController::class, 'publicStore'])->name('feedback.public.store');

// Public Display Screen (full-screen slideshow, no auth) — المحتوى حسب الـ tenant في الرابط
Route::get('/display/{tenant?}', [DisplayScreenController::class, 'show'])->name('display.screen');
