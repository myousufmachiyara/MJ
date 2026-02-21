<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\{
    DashboardController,
    SubHeadOfAccController,
    COAController,
    SaleInvoiceController,
    ProductionController,
    PurchaseInvoiceController,
    PurchaseReturnController,
    ProductController,
    UserController,
    RoleController,
    AttributeController,
    PurityController,
    ProductCategoryController,
    ProductionReceivingController,
    VoucherController,
    InventoryReportController,
    PurchaseReportController,
    ProductionReportController,
    SalesReportController,
    AccountsReportController,
    SaleReturnController,
    PermissionController,
    ProductionReturnController,
    ProductSubcategoryController,
};

Auth::routes();

Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::put('/users/{id}/change-password', [UserController::class, 'changePassword'])->name('users.changePassword');
    Route::put('/users/{id}/toggle-active',   [UserController::class, 'toggleActive'])->name('users.toggleActive');

    // Product Helpers
    Route::get('/products/details',                    [ProductController::class,         'details'])->name('products.receiving');
    Route::get('/product/{product}/variations',        [ProductController::class,         'getVariations'])->name('product.variations');
    Route::get('/product/{product}/productions',       [ProductionController::class,      'getProductProductions'])->name('product.productions');
    Route::get('/get-subcategories/{category_id}',     [ProductCategoryController::class, 'getSubcategories'])->name('products.getSubcategories');

    // Purchase Helpers
    Route::get('/product/{product}/invoices',          [PurchaseInvoiceController::class, 'getProductInvoices']);
    Route::get('/purchase-invoices/download-template', [PurchaseInvoiceController::class, 'downloadTemplate'])->name('purchase.download_template');
    Route::get('/purchase-invoices/{id}/barcodes',     [PurchaseInvoiceController::class, 'printBarcodes'])->name('purchase_invoices.barcodes');

    // Production Helpers
    Route::get('/production-summary/{id}',  [ProductionController::class, 'summary'])->name('production.summary');
    Route::get('/production-gatepass/{id}', [ProductionController::class, 'printGatepass'])->name('production.gatepass');

    // Sale Helpers
    Route::get('/sale-invoices/scan-barcode', [SaleInvoiceController::class, 'scanBarcode'])->name('sale.scan_barcode');

    // ── Purities (embedded in attributes page, guarded by attributes permission) ──
    Route::post  ('purities',      [PurityController::class, 'store'])  ->middleware('check.permission:attributes.create')->name('purities.store');
    Route::put   ('purities/{id}', [PurityController::class, 'update']) ->middleware('check.permission:attributes.edit')  ->name('purities.update');
    Route::delete('purities/{id}', [PurityController::class, 'destroy'])->middleware('check.permission:attributes.delete')->name('purities.destroy');

    // ── Vouchers ──────────────────────────────────────────────────────────────
    Route::redirect('/vouchers', '/vouchers/purchase')->name('vouchers.default');

    Route::prefix('vouchers/{type}')->group(function () {
        Route::get('/',          [VoucherController::class, 'index'])  ->middleware('check.permission:vouchers.index') ->name('vouchers.index');
        Route::get('/create',    [VoucherController::class, 'create']) ->middleware('check.permission:vouchers.create')->name('vouchers.create');
        Route::post('/',         [VoucherController::class, 'store'])  ->middleware('check.permission:vouchers.create')->name('vouchers.store');
        Route::get('/{id}',      [VoucherController::class, 'show'])   ->middleware('check.permission:vouchers.index') ->name('vouchers.show');
        Route::get('/{id}/edit', [VoucherController::class, 'edit'])   ->middleware('check.permission:vouchers.edit')  ->name('vouchers.edit');
        Route::put('/{id}',      [VoucherController::class, 'update']) ->middleware('check.permission:vouchers.edit')  ->name('vouchers.update');
        Route::delete('/{id}',   [VoucherController::class, 'destroy'])->middleware('check.permission:vouchers.delete')->name('vouchers.destroy');
        Route::get('/{id}/print',[VoucherController::class, 'print'])  ->middleware('check.permission:vouchers.print') ->name('vouchers.print');
    });

    // ── All other modules ─────────────────────────────────────────────────────
    $modules = [
        'roles'                 => ['controller' => RoleController::class,               'permission' => 'user_roles'],
        'permissions'           => ['controller' => PermissionController::class,         'permission' => 'role_permissions'],
        'users'                 => ['controller' => UserController::class,               'permission' => 'users'],
        'coa'                   => ['controller' => COAController::class,                'permission' => 'coa'],
        'shoa'                  => ['controller' => SubHeadOfAccController::class,       'permission' => 'shoa'],
        'products'              => ['controller' => ProductController::class,            'permission' => 'products'],
        'product_categories'    => ['controller' => ProductCategoryController::class,    'permission' => 'product_categories'],
        'product_subcategories' => ['controller' => ProductSubcategoryController::class, 'permission' => 'product_subcategories'],
        'attributes'            => ['controller' => AttributeController::class,          'permission' => 'attributes'],
        'purchase_invoices'     => ['controller' => PurchaseInvoiceController::class,    'permission' => 'purchase_invoices'],
        'purchase_return'       => ['controller' => PurchaseReturnController::class,     'permission' => 'purchase_return'],
        'sale_invoices'         => ['controller' => SaleInvoiceController::class,        'permission' => 'sale_invoices'],
        'sale_return'           => ['controller' => SaleReturnController::class,         'permission' => 'sale_return'],
        'production'            => ['controller' => ProductionController::class,         'permission' => 'production'],
        'production_receiving'  => ['controller' => ProductionReceivingController::class,'permission' => 'production_receiving'],
        'production_return'     => ['controller' => ProductionReturnController::class,   'permission' => 'production_return'],
    ];

    foreach ($modules as $uri => $config) {
        $controller = $config['controller'];
        $permission = $config['permission'];
        $param      = $uri === 'roles' ? '{role}' : '{id}';

        Route::get("$uri",              [$controller, 'index'])  ->middleware("check.permission:$permission.index")  ->name("$uri.index");
        Route::get("$uri/create",       [$controller, 'create']) ->middleware("check.permission:$permission.create") ->name("$uri.create");
        Route::post("$uri",             [$controller, 'store'])  ->middleware("check.permission:$permission.create") ->name("$uri.store");
        Route::get("$uri/$param",       [$controller, 'show'])   ->middleware("check.permission:$permission.index")  ->name("$uri.show");
        Route::get("$uri/$param/edit",  [$controller, 'edit'])   ->middleware("check.permission:$permission.edit")   ->name("$uri.edit");
        Route::put("$uri/$param",       [$controller, 'update']) ->middleware("check.permission:$permission.edit")   ->name("$uri.update");
        Route::delete("$uri/$param",    [$controller, 'destroy'])->middleware("check.permission:$permission.delete") ->name("$uri.destroy");
        Route::get("$uri/$param/print", [$controller, 'print'])  ->middleware("check.permission:$permission.print")  ->name("$uri.print");
    }

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('inventory',  [InventoryReportController::class,  'inventoryReports'])->name('inventory');
        Route::get('purchase',   [PurchaseReportController::class,   'purchaseReports']) ->name('purchase');
        Route::get('production', [ProductionReportController::class, 'productionReports'])->name('production');
        Route::get('sale',       [SalesReportController::class,      'saleReports'])     ->name('sale');
        Route::get('accounts',   [AccountsReportController::class,   'accounts'])        ->name('accounts');
    });
});