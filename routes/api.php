<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SafeController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CostCenterController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ItemCategoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\KaratController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use App\Models\AccountTemplate;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/auth/check',    [AuthController::class, 'checkCredentials']);
Route::post('/auth/login',    [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/check-mobile', [AuthController::class, 'checkMobile']);


Route::get('/accounts-template', function () {
    $accounts = AccountTemplate::orderBy('sort_order')->get();
    return response()->json([
        'success' => true,
        'count'   => $accounts->count(),
        'data'    => $accounts,
    ]);
});

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get ('/auth/me',     [AuthController::class, 'me']);

    Route::middleware(['auth:sanctum', 'permission:reports.accounts'])->get('/dashboard/stats', [DashboardController::class, 'stats']);
    // ==================== الشركات ====================
    Route::middleware('permission:companies.view')->group(function () {
        Route::get   ('/companies',           [CompanyController::class, 'index']);
        Route::get   ('/companies/create',    [CompanyController::class, 'create']);
        Route::get   ('/companies/{id}',      [CompanyController::class, 'show'])->whereNumber('id');
        Route::get   ('/companies/{id}/edit', [CompanyController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:companies.create')->post  ('/companies', [CompanyController::class, 'store']);
    Route::middleware('permission:companies.edit')->put     ('/companies/{id}', [CompanyController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:companies.delete')->delete('/companies/{id}', [CompanyController::class, 'destroy'])->whereNumber('id');
    Route::middleware('permission:companies.delete')->delete('/companies/{id}/force', [CompanyController::class, 'forceDelete'])->whereNumber('id');
    Route::middleware('permission:companies.view')->post   ('/companies/{id}/impersonate', [CompanyController::class, 'impersonate'])->whereNumber('id');




    Route::middleware('permission:companies.create')->post  ('/companies', [CompanyController::class, 'store']);
    Route::middleware('permission:companies.edit')->put     ('/companies/{id}', [CompanyController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:companies.delete')->delete('/companies/{id}', [CompanyController::class, 'destroy'])->whereNumber('id');

    // ==================== المستخدمون ====================
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users',           [UserController::class, 'index']);
        Route::get('/users/create',    [UserController::class, 'create']);
        Route::get('/users/{id}',      [UserController::class, 'show'])->whereNumber('id');
        Route::get('/users/{id}/edit', [UserController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:users.create')->post  ('/users', [UserController::class, 'store']);
    Route::middleware('permission:users.edit')->put     ('/users/{id}', [UserController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:users.delete')->delete('/users/{id}', [UserController::class, 'destroy'])->whereNumber('id');
    Route::middleware('permission:users.toggle')->post  ('/users/{id}/toggle', [UserController::class, 'toggleActive'])->whereNumber('id');
    Route::middleware('permission:users.password')->post('/users/{id}/password', [UserController::class, 'changePassword'])->whereNumber('id');

    // ==================== الأدوار ====================
    Route::middleware('permission:roles.view')->group(function () {
        Route::get('/roles',           [RoleController::class, 'index']);
        Route::get('/roles/create',    [RoleController::class, 'create']);
        Route::get('/roles/{id}',      [RoleController::class, 'show'])->whereNumber('id');
        Route::get('/roles/{id}/edit', [RoleController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:roles.create')->post  ('/roles', [RoleController::class, 'store']);
    Route::middleware('permission:roles.edit')->put     ('/roles/{id}', [RoleController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:roles.delete')->delete('/roles/{id}', [RoleController::class, 'destroy'])->whereNumber('id');

    // ==================== صلاحيات المستخدمين ====================
    Route::middleware('permission:roles.view')->group(function () {
        Route::get ('/permissions',               [PermissionController::class, 'index']);
        Route::get ('/permissions/tree',          [PermissionController::class, 'tree']);
        Route::get ('/permissions/user/{userId}', [PermissionController::class, 'getUserPermissions'])->whereNumber('userId');
    });
    Route::middleware('permission:roles.edit')->post('/permissions/user/{userId}', [PermissionController::class, 'saveUserPermissions'])->whereNumber('userId');

// ==================== رفع الصور ====================
    Route::post  ('/upload', [UploadController::class, 'upload']);
    Route::delete('/upload', [UploadController::class, 'delete']);

    // ==================== الحسابات ====================
    Route::middleware('permission:accounts.view')->group(function () {
        Route::get('/accounts',                [AccountController::class, 'index']);
        Route::get('/accounts/tree',           [AccountController::class, 'tree']);
        Route::get('/accounts/create',         [AccountController::class, 'create']);
        Route::get('/accounts/acc-no/{accNo}', [AccountController::class, 'byAccNo']);
        Route::get('/accounts/name/{name}',    [AccountController::class, 'byName']);
        Route::get('/accounts/{id}',           [AccountController::class, 'show'])->whereNumber('id');
        Route::get('/accounts/{id}/edit',      [AccountController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:accounts.create')->post  ('/accounts', [AccountController::class, 'store']);
    Route::middleware('permission:accounts.edit')->put     ('/accounts/{id}', [AccountController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:accounts.delete')->delete('/accounts/{id}', [AccountController::class, 'destroy'])->whereNumber('id');

    // ==================== القيود ====================
    Route::middleware('permission:journal.view')->group(function () {
        Route::get('/journal-entries',           [JournalEntryController::class, 'index']);
        Route::get('/journal-entries/create',    [JournalEntryController::class, 'create']);
        Route::get('/journal-entries/{id}',      [JournalEntryController::class, 'show'])->whereNumber('id');
        Route::get('/journal-entries/{id}/edit', [JournalEntryController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:journal.create')->post  ('/journal-entries', [JournalEntryController::class, 'store']);
    Route::middleware('permission:journal.edit')->put     ('/journal-entries/{id}', [JournalEntryController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:journal.delete')->delete('/journal-entries/{id}', [JournalEntryController::class, 'destroy'])->whereNumber('id');
    Route::middleware('permission:journal.post')->post    ('/journal-entries/{id}/post', [JournalEntryController::class, 'post'])->whereNumber('id');
    Route::middleware('permission:journal.reverse')->post ('/journal-entries/{id}/reverse', [JournalEntryController::class, 'reverse'])->whereNumber('id');

    // ==================== العملاء والموردون ====================
    Route::middleware('permission:contacts.view')->group(function () {
        Route::get('/contacts',           [ContactController::class, 'index']);
        Route::get('/contacts/create',    [ContactController::class, 'create']);
        Route::get('/contacts/{id}',      [ContactController::class, 'show'])->whereNumber('id');
        Route::get('/contacts/{id}/edit', [ContactController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:contacts.create')->post  ('/contacts', [ContactController::class, 'store']);
    Route::middleware('permission:contacts.edit')->put     ('/contacts/{id}', [ContactController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:contacts.delete')->delete('/contacts/{id}', [ContactController::class, 'destroy'])->whereNumber('id');

    // ==================== الصناديق ====================
    Route::middleware('permission:safes.view')->group(function () {
        Route::get('/safes',      [SafeController::class, 'index']);
        Route::get('/safes/{id}', [SafeController::class, 'show'])->whereNumber('id');
    });
    Route::middleware('permission:safes.create')->post  ('/safes', [SafeController::class, 'store']);
    Route::middleware('permission:safes.edit')->put     ('/safes/{id}', [SafeController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:safes.delete')->delete('/safes/{id}', [SafeController::class, 'destroy'])->whereNumber('id');

    // ==================== البنوك ====================
    Route::middleware('permission:banks.view')->group(function () {
        Route::get('/banks',           [BankController::class, 'index']);
        Route::get('/banks/create',    [BankController::class, 'create']);
        Route::get('/banks/{id}',      [BankController::class, 'show'])->whereNumber('id');
        Route::get('/banks/{id}/edit', [BankController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:banks.create')->post  ('/banks', [BankController::class, 'store']);
    Route::middleware('permission:banks.edit')->put     ('/banks/{id}', [BankController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:banks.delete')->delete('/banks/{id}', [BankController::class, 'destroy'])->whereNumber('id');

    // ==================== الفروع ====================
    Route::middleware('permission:branches.view')->group(function () {
        Route::get('/branches',      [BranchController::class, 'index']);
        Route::get('/branches/{id}', [BranchController::class, 'show'])->whereNumber('id');
    });
    Route::middleware('permission:branches.create')->post  ('/branches', [BranchController::class, 'store']);
    Route::middleware('permission:branches.edit')->put     ('/branches/{id}', [BranchController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:branches.delete')->delete('/branches/{id}', [BranchController::class, 'destroy'])->whereNumber('id');

    // ==================== مراكز التكلفة ====================
    Route::middleware('permission:cost_centers.view')->group(function () {
        Route::get('/cost-centers',           [CostCenterController::class, 'index']);
        Route::get('/cost-centers/create',    [CostCenterController::class, 'create']);
        Route::get('/cost-centers/{id}',      [CostCenterController::class, 'show'])->whereNumber('id');
        Route::get('/cost-centers/{id}/edit', [CostCenterController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:cost_centers.create')->post  ('/cost-centers', [CostCenterController::class, 'store']);
    Route::middleware('permission:cost_centers.edit')->put     ('/cost-centers/{id}', [CostCenterController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:cost_centers.delete')->delete('/cost-centers/{id}', [CostCenterController::class, 'destroy'])->whereNumber('id');

    // ==================== الضرائب ====================
    Route::middleware('permission:taxes.view')->group(function () {
        Route::get('/taxes',      [TaxController::class, 'index']);
        Route::get('/taxes/{id}', [TaxController::class, 'show'])->whereNumber('id');
    });
    Route::middleware('permission:taxes.create')->post  ('/taxes', [TaxController::class, 'store']);
    Route::middleware('permission:taxes.edit')->put     ('/taxes/{id}', [TaxController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:taxes.delete')->delete('/taxes/{id}', [TaxController::class, 'destroy'])->whereNumber('id');

    // ==================== العملات ====================
    Route::middleware('permission:currencies.view')->group(function () {
        Route::get('/currencies',      [CurrencyController::class, 'index']);
        Route::get('/currencies/{id}', [CurrencyController::class, 'show'])->whereNumber('id');
    });
    Route::middleware('permission:currencies.create')->post  ('/currencies', [CurrencyController::class, 'store']);
    Route::middleware('permission:currencies.edit')->put     ('/currencies/{id}', [CurrencyController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:currencies.delete')->delete('/currencies/{id}', [CurrencyController::class, 'destroy'])->whereNumber('id');

    // ==================== الأصناف ====================
    Route::middleware('permission:items.view')->group(function () {
        Route::get('/items',           [ItemController::class, 'index']);
        Route::get('/items/create',    [ItemController::class, 'create']);
        Route::get('/items/{id}',      [ItemController::class, 'show'])->whereNumber('id');
        Route::get('/items/{id}/edit', [ItemController::class, 'edit'])->whereNumber('id');
        Route::get('/items/next-barcode', [ItemController::class, 'nextBarcode']);
    });
    Route::middleware('permission:items.create')->post  ('/items', [ItemController::class, 'store']);
    Route::middleware('permission:items.edit')->put     ('/items/{id}', [ItemController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:items.delete')->delete('/items/{id}', [ItemController::class, 'destroy'])->whereNumber('id');

    // ==================== مجموعات الأصناف ====================
    Route::middleware('permission:item_categories.view')->group(function () {
        Route::get('/item-categories',           [ItemCategoryController::class, 'index']);
        Route::get('/item-categories/tree',      [ItemCategoryController::class, 'tree']);
        Route::get('/item-categories/create',    [ItemCategoryController::class, 'create']);
        Route::get('/item-categories/{id}',      [ItemCategoryController::class, 'show'])->whereNumber('id');
        Route::get('/item-categories/{id}/edit', [ItemCategoryController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:item_categories.create')->post  ('/item-categories', [ItemCategoryController::class, 'store']);
    Route::middleware('permission:item_categories.edit')->put     ('/item-categories/{id}', [ItemCategoryController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:item_categories.delete')->delete('/item-categories/{id}', [ItemCategoryController::class, 'destroy'])->whereNumber('id');

    // ==================== العيارات ====================
    Route::middleware('permission:karats.view')->group(function () {
        Route::get('/karats',           [KaratController::class, 'index']);
        Route::get('/karats/create',    [KaratController::class, 'create']);
        Route::get('/karats/{id}',      [KaratController::class, 'show'])->whereNumber('id');
        Route::get('/karats/{id}/edit', [KaratController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:karats.create')->post  ('/karats', [KaratController::class, 'store']);
    Route::middleware('permission:karats.edit')->put     ('/karats/{id}', [KaratController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:karats.delete')->delete('/karats/{id}', [KaratController::class, 'destroy'])->whereNumber('id');

    // ==================== المخازن ====================
    Route::middleware('permission:stores.view')->group(function () {
        Route::get('/stores',           [StoreController::class, 'index']);
        Route::get('/stores/list',      [StoreController::class, 'list']);
        Route::get('/stores/create',    [StoreController::class, 'create']);
        Route::get('/stores/{id}',      [StoreController::class, 'show'])->whereNumber('id');
        Route::get('/stores/{id}/edit', [StoreController::class, 'edit'])->whereNumber('id');
    });
    Route::middleware('permission:stores.create')->post  ('/stores', [StoreController::class, 'store']);
    Route::middleware('permission:stores.edit')->put     ('/stores/{id}', [StoreController::class, 'update'])->whereNumber('id');
    Route::middleware('permission:stores.delete')->delete('/stores/{id}', [StoreController::class, 'destroy'])->whereNumber('id');
});












//     Route::get('/gen-hash', function () {
//     return response()->json(['hash' => \Illuminate\Support\Facades\Hash::make('123')]);
// });


// Route::get('/debug-login', function () {
//     $user = \App\Models\User::where('mobile', '775294208')->first();

//     if (!$user) {
//         return response()->json(['error' => 'المستخدم غير موجود']);
//     }

//     $hashCheck = \Illuminate\Support\Facades\Hash::check('123', $user->password_hash);

//     return response()->json([
//         'user_found'   => true,
//         'user_id'      => $user->id,
//         'mobile'       => $user->mobile,
//         'is_active'    => $user->is_active,
//         'company_id'   => $user->company_id,
//         'branch_id'    => $user->branch_id,
//         'hash_length'  => strlen($user->password_hash),
//         'hash_prefix'  => substr($user->password_hash, 0, 7),
//         'hash_check'   => $hashCheck,
//         'hash_full'    => $user->password_hash,
//     ]);
// });



// Route::get('/fix-pass', function () {
//     $u = \App\Models\User::where('mobile', '775294208')->first();
//     if (!$u) return response()->json(['error' => 'user not found'], 404);

//     $u->password_hash = \Illuminate\Support\Facades\Hash::make('123');
//     $u->save();

//     return response()->json([
//         'ok'    => true,
//         'check' => \Illuminate\Support\Facades\Hash::check('123', $u->password_hash),
//         'hash'  => $u->password_hash,
//     ]);
// });

