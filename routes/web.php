<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\OfferController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SalesReportController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\FridgeController;
use App\Http\Controllers\Admin\RawMaterialController;
use App\Http\Controllers\Admin\InventoryCountController;
use App\Http\Controllers\Admin\RawMaterialCategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\ExpenseController;

use App\Http\Controllers\BaristaController;

use App\Http\Controllers\CashierShiftController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\JobApplicationController as AdminJobApplicationController;
use App\Http\Controllers\Admin\DisplayScreenController;
use App\Http\Controllers\Admin\AttendanceGroupController;
use App\Http\Controllers\Admin\EmployeeAttendanceSettingsController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\TenantSettingsController;
use App\Http\Controllers\Admin\SpacesLabController;
use App\Http\Controllers\Admin\ClosingPhotoReportController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\BranchContextController;
use App\Http\Controllers\QzTrayController;

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
    'hr.employees_only',
])->group(function () {
    Route::get('/dashboard', function () {
        $user = Auth::user();
        $closingGate = null;
        if ($user) {
            $closingGate = app(\App\Services\ClosingPhotoReportService::class)->gateForUser($user);
        }

        return Inertia::render('Dashboard', [
            'canViewReports' => $user->can('sales.reports') || $user->can('view sales reports'),
            'canManageAttendance' => $user->can('employees.attendance')
                || $user->can('employees.view')
                || $user->can('manage employee attendance'),
            'canManageFeedback' => $user->can('feedback.view') || $user->can('feedback.manage'),
            'closingPhotoReportGate' => $closingGate,
        ]);
    })->name('dashboard');

    Route::get('/qz/certificate', [QzTrayController::class, 'certificate'])->name('qz.certificate');
    Route::post('/qz/sign', [QzTrayController::class, 'sign'])->name('qz.sign');

    // Barista
    Route::get('/barista', [BaristaController::class, 'index'])
        ->middleware('permission:barista.use')
        ->name('barista.index');

    // Products — المسارات الثابتة قبل {product}
    Route::middleware(['permission:products.view|products.manage'])->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('admin.products.index');
        Route::get('/products/export', [ProductController::class, 'export'])->name('admin.products.export');
    });
    Route::get('/products/cost-analysis', [ProductController::class, 'costAnalysis'])
        ->middleware('permission:products.cost_analysis')
        ->name('admin.products.cost-analysis');
    Route::get('/products/sales-analysis', [ProductController::class, 'salesAnalysis'])
        ->middleware('permission:products.sales_analysis')
        ->name('admin.products.sales-analysis');
    Route::middleware(['permission:products.manage'])->group(function () {
        Route::get('/products/create', [ProductController::class, 'create'])->name('admin.products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('admin.products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('admin.products.edit');
        Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->name('admin.products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('admin.products.destroy');
        Route::post('/products/{product}/toggle-draft', [ProductController::class, 'toggleDraft'])->name('admin.products.toggle-draft');
    });

    // Offers
    Route::middleware(['permission:offers.view|offers.manage'])->prefix('offers')->name('admin.offers.')->group(function () {
        Route::get('/', [OfferController::class, 'index'])->name('index');
    });
    Route::middleware(['permission:offers.manage'])->prefix('offers')->name('admin.offers.')->group(function () {
        Route::get('/create', [OfferController::class, 'create'])->name('create');
        Route::post('/', [OfferController::class, 'store'])->name('store');
        Route::get('/{offer}/edit', [OfferController::class, 'edit'])->name('edit');
        Route::put('/{offer}', [OfferController::class, 'update'])->name('update');
        Route::delete('/{offer}', [OfferController::class, 'destroy'])->name('destroy');
        Route::post('/{offer}/toggle', [OfferController::class, 'toggle'])->name('toggle');
    });

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])
        ->middleware('permission:categories.manage|products.view|products.manage')
        ->name('admin.categories.index');
    Route::middleware(['permission:categories.manage'])->group(function () {
        Route::post('/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
        Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('admin.categories.update');
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');
    });

    // Raw Materials — المسارات الثابتة قبل {raw_material}
    Route::middleware(['permission:inventory.counts'])->group(function () {
        Route::get('/raw-materials/inventory-counts', [InventoryCountController::class, 'index'])
            ->name('admin.raw-materials.inventory-counts.index');
        Route::post('/raw-materials/inventory-counts/branches/{branch}/start', [InventoryCountController::class, 'start'])
            ->name('admin.raw-materials.inventory-counts.start');
        Route::get('/raw-materials/inventory-counts/{inventoryCount}/pdf', [InventoryCountController::class, 'pdf'])
            ->name('admin.raw-materials.inventory-counts.pdf');
        Route::get('/raw-materials/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'show'])
            ->name('admin.raw-materials.inventory-counts.show');
        Route::put('/raw-materials/inventory-counts/{inventoryCount}/items/{item}', [InventoryCountController::class, 'updateItem'])
            ->name('admin.raw-materials.inventory-counts.items.update');
        Route::delete('/raw-materials/inventory-counts/{inventoryCount}/items/{item}', [InventoryCountController::class, 'clearItem'])
            ->name('admin.raw-materials.inventory-counts.items.clear');
        Route::post('/raw-materials/inventory-counts/{inventoryCount}/complete', [InventoryCountController::class, 'complete'])
            ->name('admin.raw-materials.inventory-counts.complete');
        Route::post('/raw-materials/inventory-counts/{inventoryCount}/cancel', [InventoryCountController::class, 'cancel'])
            ->name('admin.raw-materials.inventory-counts.cancel');
    });

    Route::middleware(['permission:inventory.fridge_manage|inventory.manage|inventory.view'])->group(function () {
        Route::get('/raw-materials/fridge/ingredients', [FridgeController::class, 'productIngredients'])->name('admin.fridge.ingredients');
        Route::get('/raw-materials/fridge/labels/{label}/print', [FridgeController::class, 'printLabel'])->name('admin.fridge.labels.print');
    });
    Route::middleware(['permission:inventory.fridge_manage|inventory.manage'])->group(function () {
        Route::post('/raw-materials/fridge/configs', [FridgeController::class, 'storeConfig'])->name('admin.fridge.configs.store');
        Route::put('/raw-materials/fridge/configs/{config}', [FridgeController::class, 'updateConfig'])->name('admin.fridge.configs.update');
        Route::post('/raw-materials/fridge/configs/{config}/archive', [FridgeController::class, 'archiveConfig'])->name('admin.fridge.configs.archive');
        Route::post('/raw-materials/fridge/configs/{config}/restore', [FridgeController::class, 'restoreConfig'])->name('admin.fridge.configs.restore');
        Route::post('/raw-materials/fridge/configs/{config}/closing-count-exclusion', [FridgeController::class, 'toggleClosingCountExclusion'])->name('admin.fridge.configs.closing-count-exclusion');
        Route::delete('/raw-materials/fridge/configs/{config}', [FridgeController::class, 'destroyConfig'])->name('admin.fridge.configs.destroy');
        Route::post('/raw-materials/fridge/combined-labels', [FridgeController::class, 'storeCombinedLabel'])->name('admin.fridge.combined-labels.store');
        Route::post('/raw-materials/fridge/configs/{config}/labels', [FridgeController::class, 'storeLabel'])->name('admin.fridge.labels.store');
        Route::put('/raw-materials/fridge/branch/{branch}/stocks/{config}', [FridgeController::class, 'updateBranchStock'])
            ->name('admin.fridge.branch-stock.update');
    });

    Route::middleware(['permission:inventory.view|inventory.manage'])->group(function () {
        Route::get('/raw-materials/pending-receive', [RawMaterialController::class, 'receiveByBarcodeForm'])->name('admin.raw-materials.pending-receive');
        Route::get('/raw-materials/labels/{label}/print', [RawMaterialController::class, 'printLabel'])->name('admin.raw-materials.labels.print');
        Route::get('/raw-materials/branch-pulls-report', [RawMaterialController::class, 'allBranchesPullsReport'])->name('admin.raw-materials.branch-pulls-report');
        Route::get('/raw-materials', [RawMaterialController::class, 'index'])->name('admin.raw-materials.index');
    });
    Route::middleware(['permission:inventory.manage'])->group(function () {
        Route::post('/raw-materials/pending-receive', [RawMaterialController::class, 'receiveByBarcode'])->name('admin.raw-materials.pending-receive.store');
        Route::post('/raw-materials/combined-labels', [RawMaterialController::class, 'storeCombinedLabel'])->name('admin.raw-materials.combined-labels.store');
        Route::get('/raw-materials/create', [RawMaterialController::class, 'create'])->name('admin.raw-materials.create');
        Route::post('/raw-materials', [RawMaterialController::class, 'store'])->name('admin.raw-materials.store');
        Route::put('/raw-materials/branch/{branch}/stock/{raw_material}', [RawMaterialController::class, 'updateBranchStock'])
            ->name('admin.raw-materials.branch-stock.update');
        Route::get('/raw-material-categories', [RawMaterialCategoryController::class, 'index'])->name('admin.raw-material-categories.index');
        Route::post('/raw-material-categories', [RawMaterialCategoryController::class, 'store'])->name('admin.raw-material-categories.store');
        Route::put('/raw-material-categories/{category}', [RawMaterialCategoryController::class, 'update'])->name('admin.raw-material-categories.update');
        Route::delete('/raw-material-categories/{category}', [RawMaterialCategoryController::class, 'destroy'])->name('admin.raw-material-categories.destroy');
        // parameterized after static paths
        Route::post('/raw-materials/{raw_material}/labels', [RawMaterialController::class, 'storeLabel'])->name('admin.raw-materials.labels.store');
        Route::get('raw-materials/{raw_material}/add-quantity', [RawMaterialController::class, 'addQuantityForm'])->name('admin.raw-materials.add-quantity');
        Route::post('raw-materials/{raw_material}/add-quantity', [RawMaterialController::class, 'addQuantity'])->name('admin.raw-materials.add-quantity.store');
        Route::match(['put', 'patch'], '/raw-materials/{raw_material}', [RawMaterialController::class, 'update'])->name('admin.raw-materials.update');
        Route::delete('/raw-materials/{raw_material}', [RawMaterialController::class, 'destroy'])->name('admin.raw-materials.destroy');
    });
    Route::get('/raw-materials/{raw_material}/edit', [RawMaterialController::class, 'edit'])
        ->middleware('permission:inventory.view|inventory.manage')
        ->name('admin.raw-materials.edit');

    // Users
    Route::middleware(['permission:users.view|users.manage'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    });
    Route::middleware(['permission:users.manage'])->group(function () {
        Route::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
    });

    // Roles / teams
    Route::middleware(['permission:roles.manage'])->group(function () {
        Route::get('/admin/roles', [RolePermissionController::class, 'index'])->name('admin.roles.index');
        Route::post('/admin/roles', [RolePermissionController::class, 'store'])->name('admin.roles.store');
        Route::put('/admin/roles/{role}', [RolePermissionController::class, 'update'])->name('admin.roles.update');
        Route::delete('/admin/roles/{role}', [RolePermissionController::class, 'destroy'])->name('admin.roles.destroy');
    });

    // Branch context switch
    Route::middleware(['permission:branches.switch'])->group(function () {
        Route::post('/branch-context/select/{branch}', [BranchContextController::class, 'select'])->name('branch-context.select');
        Route::post('/branch-context/clear', [BranchContextController::class, 'clear'])->name('branch-context.clear');
    });

    // Branches
    Route::middleware(['permission:branches.view|branches.manage'])->group(function () {
        Route::get('/admin/branches', [BranchController::class, 'index'])->name('admin.branches.index');
    });
    Route::middleware(['permission:branches.manage'])->group(function () {
        Route::post('/admin/branches', [BranchController::class, 'store'])->name('admin.branches.store');
        Route::put('/admin/branches/{branch}', [BranchController::class, 'update'])->name('admin.branches.update');
        Route::delete('/admin/branches/{branch}', [BranchController::class, 'destroy'])->name('admin.branches.destroy');
    });

    // Display Screen
    Route::middleware(['permission:display.manage'])->prefix('admin/display-screen')->name('admin.display-screen.')->group(function () {
        Route::get('/', [DisplayScreenController::class, 'index'])->name('index');
        Route::post('/slides', [DisplayScreenController::class, 'storeSlide'])->name('slides.store');
        Route::put('/slides/order', [DisplayScreenController::class, 'updateOrder'])->name('slides.order');
        Route::put('/slides/{slide}', [DisplayScreenController::class, 'updateSlide'])->name('slides.update');
        Route::delete('/slides/{slide}', [DisplayScreenController::class, 'destroySlide'])->name('slides.destroy');
    });

    // إعدادات البراند
    Route::middleware(['permission:tenant.settings'])->prefix('admin/settings')->name('admin.tenant-settings.')->group(function () {
        Route::get('/', [TenantSettingsController::class, 'index'])->name('index');
        Route::post('/logo', [TenantSettingsController::class, 'updateLogo'])->name('logo.update');
        Route::delete('/logo', [TenantSettingsController::class, 'destroyLogo'])->name('logo.destroy');
        Route::put('/branches/{branch}/printers', [TenantSettingsController::class, 'updateBranchPrinters'])->name('branches.printers.update');
        Route::put('/fixed-salary-withdraw-limit', [TenantSettingsController::class, 'updateFixedSalaryWithdrawLimit'])->name('fixed-salary-withdraw-limit.update');
        Route::post('/qz-keys', [TenantSettingsController::class, 'uploadQzKeys'])->name('qz-keys.upload');
        Route::get('/qz-trust-package', [QzTrayController::class, 'downloadTrustPackage'])->name('qz-trust-package.download');
    });

    // معمل Spaces — أداة تقنية تبقى للسوبر أدمن
    Route::middleware(['super_admin'])->prefix('admin/spaces-lab')->name('admin.spaces-lab.')->group(function () {
        Route::get('/', [SpacesLabController::class, 'index'])->name('index');
        Route::post('/', [SpacesLabController::class, 'store'])->name('store');
        Route::put('/', [SpacesLabController::class, 'update'])->name('update');
        Route::delete('/', [SpacesLabController::class, 'destroy'])->name('destroy');
    });

    // صور التقفيلة — إعدادات
    Route::middleware(['permission:closing_photos.settings'])->prefix('admin/closing-photo-reports')->name('admin.closing-photo-reports.')->group(function () {
        Route::get('/settings', [ClosingPhotoReportController::class, 'settings'])->name('settings');
        Route::put('/settings', [ClosingPhotoReportController::class, 'updateSettings'])->name('settings.update');
        Route::post('/items', [ClosingPhotoReportController::class, 'storeItem'])->name('items.store');
        Route::put('/items/{item}', [ClosingPhotoReportController::class, 'updateItem'])->name('items.update');
        Route::delete('/items/{item}', [ClosingPhotoReportController::class, 'destroyItem'])->name('items.destroy');
    });

    Route::middleware(['permission:closing_photos.submit'])->prefix('admin/closing-photo-reports')->name('admin.closing-photo-reports.')->group(function () {
        Route::get('/submit', [ClosingPhotoReportController::class, 'submitForm'])->name('submit');
        Route::post('/upload', [ClosingPhotoReportController::class, 'upload'])->name('upload');
        Route::post('/fridge-count', [ClosingPhotoReportController::class, 'storeFridgeCount'])->name('fridge-count');
    });

    Route::get('/admin/closing-photo-reports/browse', [ClosingPhotoReportController::class, 'browse'])
        ->middleware('permission:closing_photos.browse')
        ->name('admin.closing-photo-reports.browse');

    Route::middleware(['branch.context'])->group(function () {
        Route::middleware(['permission:inventory.branch_pull'])->group(function () {
            Route::get('/raw-materials/branch-pull', [RawMaterialController::class, 'branchPullForm'])->name('admin.raw-materials.branch-pull');
            Route::post('/raw-materials/branch-pull', [RawMaterialController::class, 'branchPullStore'])->name('admin.raw-materials.branch-pull.store');
        });
        Route::middleware(['permission:inventory.fridge_pull'])->group(function () {
            Route::get('/raw-materials/fridge-pull', [FridgeController::class, 'fridgePullForm'])->name('admin.fridge.pull');
            Route::post('/raw-materials/fridge-pull', [FridgeController::class, 'fridgePullStore'])->name('admin.fridge.pull.store');
        });

        // Employees — المسارات الثابتة قبل {employee}
        Route::middleware(['permission:employees.view|employees.manage|employees.attendance'])->group(function () {
            Route::get('/employees', [EmployeeController::class, 'index'])->name('admin.employees.index');
        });

        Route::middleware(['permission:employees.manage'])->group(function () {
            Route::get('/employees/create', [EmployeeController::class, 'create'])->name('admin.employees.create');
            Route::post('/employees', [EmployeeController::class, 'store'])->name('admin.employees.store');
        });

        Route::middleware(['permission:employees.salaries'])->group(function () {
            Route::get('/employees/salary-withdrawals', [EmployeeController::class, 'salaryWithdrawals'])->name('admin.employees.salary-withdrawals');
            Route::get('/employees/salary-calculator', [EmployeeController::class, 'salaryCalculator'])->name('admin.employees.salary-calculator');
        });

        Route::middleware(['permission:employees.view|employees.manage|employees.attendance'])->group(function () {
            Route::get('/employees/{employee}/report', [EmployeeController::class, 'report'])->name('admin.employees.report');
            Route::get('/employees/{employee}/fixed-salary-month-summary', [EmployeeController::class, 'fixedSalaryMonthSummary'])->name('admin.employees.fixed-salary-month-summary');
        });

        Route::middleware(['permission:employees.manage'])->group(function () {
            Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('admin.employees.edit');
            Route::match(['put', 'patch'], '/employees/{employee}', [EmployeeController::class, 'update'])->name('admin.employees.update');
        });

        Route::middleware(['permission:employees.delete'])->group(function () {
            Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('admin.employees.destroy');
        });

        Route::middleware(['permission:employees.attendance'])->group(function () {
            Route::post('/employees/{employee}/checkin', [EmployeeController::class, 'checkin'])->name('admin.employees.checkin');
            Route::post('/employees/{employee}/checkout', [EmployeeController::class, 'checkout'])->name('admin.employees.checkout');
        });

        Route::middleware(['permission:employees.salaries'])->group(function () {
            Route::post('/employees/{employee}/calculate-salary', [EmployeeController::class, 'calculateSalary'])->name('admin.employees.calculate-salary');
            Route::post('/employees/{employee}/deliver-salary', [EmployeeController::class, 'deliverSalary'])->name('admin.employees.deliver-salary');
            Route::post('/employees/{employee}/undo-salary-delivery', [EmployeeController::class, 'undoSalaryDelivery'])->name('admin.employees.undo-salary-delivery');
            Route::post('/employees/{employee}/deliver-salary-for-date', [EmployeeController::class, 'deliverSalaryForDate'])->name('admin.employees.deliver-salary-for-date');
            Route::post('/employees/{employee}/deliver-salary-for-period', [EmployeeController::class, 'deliverSalaryForPeriod'])->name('admin.employees.deliver-salary-for-period');
            Route::post('/employees/{employee}/undo-salary-delivery-for-date', [EmployeeController::class, 'undoSalaryDeliveryForDate'])->name('admin.employees.undo-salary-delivery-for-date');
            Route::post('/employees/{employee}/withdraw-salary', [EmployeeController::class, 'withdrawSalary'])->name('admin.employees.withdraw-salary');
            Route::delete('/employees/{employee}/salary-withdrawals/{withdrawal}', [EmployeeController::class, 'cancelSalaryWithdrawal'])->name('admin.employees.salary-withdrawals.cancel');
            Route::post('/employees/{employee}/fixed-salary-debt-carryover', [EmployeeController::class, 'manageFixedSalaryDebtCarryover'])->name('admin.employees.fixed-salary-debt-carryover');
        });

        Route::middleware(['permission:employees.discounts'])->group(function () {
            Route::post('/employees/{employee}/add-discount', [EmployeeController::class, 'addDiscount'])->name('admin.employees.add-discount');
        });

        Route::middleware(['permission:employees.manage_penalties'])->group(function () {
            Route::delete('/employees/{employee}/discounts/{discount}', [EmployeeController::class, 'removeDiscount'])->name('admin.employees.remove-discount');
            Route::post('/employees/{employee}/absence-day-penalty', [EmployeeController::class, 'manageAbsenceDayPenalty'])->name('admin.employees.absence-day-penalty');
        });

        Route::middleware(['permission:employees.attendance_groups'])->prefix('employees/attendance-groups')->name('admin.employees.attendance-groups.')->group(function () {
            Route::get('/', [AttendanceGroupController::class, 'index'])->name('index');
            Route::post('/', [AttendanceGroupController::class, 'store'])->name('store');
            Route::put('/{attendanceGroup}', [AttendanceGroupController::class, 'update'])->name('update');
            Route::delete('/{attendanceGroup}', [AttendanceGroupController::class, 'destroy'])->name('destroy');
        });

        Route::middleware(['permission:employees.attendance_settings'])->prefix('employees/attendance-settings')->name('admin.employees.attendance-settings.')->group(function () {
            Route::get('/', [EmployeeAttendanceSettingsController::class, 'index'])->name('index');
            Route::post('/schedules', [EmployeeAttendanceSettingsController::class, 'updateSchedules'])->name('schedules');
            Route::post('/rules', [EmployeeAttendanceSettingsController::class, 'storeRule'])->name('rules.store');
            Route::put('/rules/{attendanceDeductionRule}', [EmployeeAttendanceSettingsController::class, 'updateRule'])->name('rules.update');
            Route::delete('/rules/{attendanceDeductionRule}', [EmployeeAttendanceSettingsController::class, 'destroyRule'])->name('rules.destroy');
        });

        // Cashier & Invoices
        Route::middleware(['permission:cashier.use'])->group(function () {
            Route::get('/cashier', [CashierController::class, 'index'])->name('cashier.index');
            Route::post('/store-order', [CashierController::class, 'store'])->name('cashier.store');
            Route::get('/invoice/{orderId}', [CashierController::class, 'invoice'])->name('invoice.show');
            Route::get('/invoice-html/{orderId}', [CashierController::class, 'invoiceHtml']);
        });

        Route::middleware(['permission:cashier.invoices|cashier.use'])->group(function () {
            Route::get('/invoices', [CashierController::class, 'invoicesToday'])->name('invoices.today');
        });

        Route::middleware(['permission:cashier.refunds'])->group(function () {
            Route::get('/cashier/refunds/recent', [CashierController::class, 'refundRecentOrders'])->name('cashier.refunds.recent');
            Route::get('/cashier/refunds/lookup', [CashierController::class, 'refundLookup'])->name('cashier.refunds.lookup');
            Route::post('/cashier/orders/{order}/refund', [CashierController::class, 'refundOrder'])->name('cashier.orders.refund');
        });

        Route::middleware(['permission:cashier.shifts|cashier.use'])->prefix('cashier-shifts')->group(function () {
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

    // Sales Report
    Route::get('/sales-report', [SalesReportController::class, 'index'])
        ->middleware('permission:sales.reports')
        ->name('admin.sales.report');

    // Purchases
    Route::middleware(['permission:purchases.view|purchases.manage'])->group(function () {
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    });
    Route::middleware(['permission:purchases.manage'])->group(function () {
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::post('/purchases/custom-items', [PurchaseController::class, 'storeCustomItem'])->name('purchases.custom-items.store');
        Route::delete('/purchases/custom-items/{customPurchaseItem}', [PurchaseController::class, 'destroyCustomItem'])->name('purchases.custom-items.destroy');
    });

    // Expenses
    Route::middleware(['permission:expenses.view|expenses.manage'])->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    });
    Route::middleware(['permission:expenses.manage'])->group(function () {
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    // Feedback
    Route::middleware(['permission:feedback.view|feedback.manage'])->group(function () {
        Route::get('/admin/feedback', [AdminFeedbackController::class, 'index'])->name('admin.feedback.index');
    });
    Route::middleware(['permission:feedback.manage'])->group(function () {
        Route::get('/admin/feedback/create', [AdminFeedbackController::class, 'create'])->name('admin.feedback.create');
        Route::post('/admin/feedback', [AdminFeedbackController::class, 'store'])->name('admin.feedback.store');
        Route::get('/admin/feedback/{feedback}/edit', [AdminFeedbackController::class, 'edit'])->name('admin.feedback.edit');
        Route::put('/admin/feedback/{feedback}', [AdminFeedbackController::class, 'update'])->name('admin.feedback.update');
        Route::delete('/admin/feedback/{feedback}', [AdminFeedbackController::class, 'destroy'])->name('admin.feedback.destroy');
        Route::put('/admin/feedback/{id}/approve', [AdminFeedbackController::class, 'approve'])->name('admin.feedback.approve');
        Route::post('/admin/feedback/bulk-action', [AdminFeedbackController::class, 'bulkAction'])->name('admin.feedback.bulk-action');
    });

    // Job applications
    Route::middleware(['permission:job_applications.view|job_applications.manage'])->group(function () {
        Route::get('/admin/job-applications', [AdminJobApplicationController::class, 'index'])->name('admin.job-applications.index');
    });
    Route::middleware(['permission:job_applications.manage'])->group(function () {
        Route::put('/admin/job-applications/{jobApplication}/note', [AdminJobApplicationController::class, 'updateNote'])->name('admin.job-applications.update-note');
        Route::delete('/admin/job-applications/{jobApplication}', [AdminJobApplicationController::class, 'destroy'])->name('admin.job-applications.destroy');
    });
});

// Public Feedback Routes
Route::get('/feedback/display/{tenant?}', [FeedbackController::class, 'publicDisplay'])->name('feedback.public.display');
Route::get('/feedback/{tenant?}', [FeedbackController::class, 'publicForm'])->name('feedback.public.form');
Route::post('/feedback', [FeedbackController::class, 'publicStore'])->name('feedback.public.store');

// Public Job Application Routes
Route::get('/jobs/apply/{tenant?}', [JobApplicationController::class, 'publicForm'])->name('job-applications.public.form');
Route::post('/jobs/apply', [JobApplicationController::class, 'publicStore'])->name('job-applications.public.store');

// Public Display Screen
Route::get('/display/{tenant?}', [DisplayScreenController::class, 'show'])->name('display.screen');
