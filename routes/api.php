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
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
// use App\Http\Controllers\Api\CostCenterController;
use App\Models\AccountTemplate;

/*
|--------------------------------------------------------------------------
| Public Routes (بدون توكن)
|--------------------------------------------------------------------------
*/

Route::post('/auth/check',    [AuthController::class, 'checkCredentials']);
Route::post('/auth/login',    [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

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
| Protected Routes (تحتاج توكن Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // ==================== الشركات (Super Admin) ====================
    Route::get   ('/companies',           [CompanyController::class, 'index']);
    Route::get   ('/companies/create',    [CompanyController::class, 'create']);
    Route::get   ('/companies/{id}',      [CompanyController::class, 'show'])->whereNumber('id');
    Route::get   ('/companies/{id}/edit', [CompanyController::class, 'edit'])->whereNumber('id');
    Route::post  ('/companies',           [CompanyController::class, 'store']);
    Route::put   ('/companies/{id}',      [CompanyController::class, 'update'])->whereNumber('id');
    Route::delete('/companies/{id}',      [CompanyController::class, 'destroy'])->whereNumber('id');

    Route::post  ('/upload', [UploadController::class, 'upload']);
    Route::delete('/upload', [UploadController::class, 'delete']);

    // ==================== المستخدمين  ====================
    Route::get   ('/users',                 [UserController::class, 'index']);
    Route::get   ('/users/create',          [UserController::class, 'create']);      // ← جديد
    Route::get   ('/users/{id}',            [UserController::class, 'show'])->whereNumber('id');
    Route::get   ('/users/{id}/edit',       [UserController::class, 'edit'])->whereNumber('id'); // ← جديد
    Route::post  ('/users',                 [UserController::class, 'store']);
    Route::put   ('/users/{id}',            [UserController::class, 'update'])->whereNumber('id');
    Route::delete('/users/{id}',            [UserController::class, 'destroy'])->whereNumber('id');
    Route::post  ('/users/{id}/toggle',     [UserController::class, 'toggleActive'])->whereNumber('id');
    Route::post  ('/users/{id}/password',   [UserController::class, 'changePassword'])->whereNumber('id');

    // ==================== الحسابات ====================
    Route::get   ('/accounts',                 [AccountController::class, 'index']);
    Route::get   ('/accounts/tree',            [AccountController::class, 'tree']);
    Route::get   ('/accounts/create',          [AccountController::class, 'create']);
    Route::get   ('/accounts/acc-no/{accNo}',  [AccountController::class, 'byAccNo']);
    Route::get   ('/accounts/name/{name}',     [AccountController::class, 'byName']);
    Route::get   ('/accounts/{id}',            [AccountController::class, 'show'])->whereNumber('id');
    Route::get   ('/accounts/{id}/edit',       [AccountController::class, 'edit'])->whereNumber('id');
    Route::post  ('/accounts',                 [AccountController::class, 'store']);
    Route::put   ('/accounts/{id}',            [AccountController::class, 'update'])->whereNumber('id');
    Route::delete('/accounts/{id}',            [AccountController::class, 'destroy'])->whereNumber('id');

    // ==================== القيود ====================
    Route::get   ('/journal-entries',              [JournalEntryController::class, 'index']);
    Route::get   ('/journal-entries/create',       [JournalEntryController::class, 'create']);
    Route::get   ('/journal-entries/{id}',         [JournalEntryController::class, 'show'])->whereNumber('id');
    Route::get   ('/journal-entries/{id}/edit',    [JournalEntryController::class, 'edit'])->whereNumber('id');
    Route::post  ('/journal-entries',              [JournalEntryController::class, 'store']);
    Route::put   ('/journal-entries/{id}',         [JournalEntryController::class, 'update'])->whereNumber('id');
    Route::delete('/journal-entries/{id}',         [JournalEntryController::class, 'destroy'])->whereNumber('id');
    Route::post  ('/journal-entries/{id}/post',    [JournalEntryController::class, 'post'])->whereNumber('id');
    Route::post  ('/journal-entries/{id}/reverse', [JournalEntryController::class, 'reverse'])->whereNumber('id');

    // ==================== العملاء والموردون ====================
    Route::get   ('/contacts',             [ContactController::class, 'index']);
    Route::get   ('/contacts/create',      [ContactController::class, 'create']);
    Route::get   ('/contacts/{id}',        [ContactController::class, 'show'])->whereNumber('id');
    Route::get   ('/contacts/{id}/edit',   [ContactController::class, 'edit'])->whereNumber('id');
    Route::post  ('/contacts',             [ContactController::class, 'store']);
    Route::put   ('/contacts/{id}',        [ContactController::class, 'update'])->whereNumber('id');
    Route::delete('/contacts/{id}',        [ContactController::class, 'destroy'])->whereNumber('id');

    // ==================== الصناديق ====================
    Route::get   ('/safes',                [SafeController::class, 'index']);
    Route::get   ('/safes/create',         [SafeController::class, 'create']);
    Route::get   ('/safes/{id}',           [SafeController::class, 'show'])->whereNumber('id');
    Route::get   ('/safes/{id}/edit',      [SafeController::class, 'edit'])->whereNumber('id');
    Route::post  ('/safes',                [SafeController::class, 'store']);
    Route::put   ('/safes/{id}',           [SafeController::class, 'update'])->whereNumber('id');
    Route::delete('/safes/{id}',           [SafeController::class, 'destroy'])->whereNumber('id');

    // ==================== البنوك ====================
    Route::get   ('/banks',                [BankController::class, 'index']);
    Route::get   ('/banks/create',         [BankController::class, 'create']);
    Route::get   ('/banks/{id}',           [BankController::class, 'show'])->whereNumber('id');
    Route::get   ('/banks/{id}/edit',      [BankController::class, 'edit'])->whereNumber('id');
    Route::post  ('/banks',                [BankController::class, 'store']);
    Route::put   ('/banks/{id}',           [BankController::class, 'update'])->whereNumber('id');
    Route::delete('/banks/{id}',           [BankController::class, 'destroy'])->whereNumber('id');

    // ==================== الفروع ====================
    Route::get   ('/branches',             [BranchController::class, 'index']);
    Route::get   ('/branches/create',      [BranchController::class, 'create']);
    Route::get   ('/branches/{id}',        [BranchController::class, 'show'])->whereNumber('id');
    Route::get   ('/branches/{id}/edit',   [BranchController::class, 'edit'])->whereNumber('id');
    Route::post  ('/branches',             [BranchController::class, 'store']);
    Route::put   ('/branches/{id}',        [BranchController::class, 'update'])->whereNumber('id');
    Route::delete('/branches/{id}',        [BranchController::class, 'destroy'])->whereNumber('id');

    // ==================== مراكز التكلفة ====================
    // Route::get   ('/cost-centers',             [CostCenterController::class, 'index']);
    // Route::get   ('/cost-centers/tree',        [CostCenterController::class, 'tree']);
    // Route::get   ('/cost-centers/{id}',        [CostCenterController::class, 'show'])->whereNumber('id');
    // Route::post  ('/cost-centers',             [CostCenterController::class, 'store']);
    // Route::put   ('/cost-centers/{id}',        [CostCenterController::class, 'update'])->whereNumber('id');
    // Route::delete('/cost-centers/{id}',        [CostCenterController::class, 'destroy'])->whereNumber('id');

    });


    Route::get('/gen-hash', function () {
    return response()->json(['hash' => \Illuminate\Support\Facades\Hash::make('123')]);
});
