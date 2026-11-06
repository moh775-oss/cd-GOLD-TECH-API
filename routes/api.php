<?php

use Illuminate\Support\Facades\Route;
use App\Models\AccountTemplate;

Route::get('/accounts-template', function () {
    $accounts = AccountTemplate::orderBy('sort_order')->get();

    return response()->json([
        'success' => true,
        'count' => $accounts->count(),
        'data' => $accounts,
    ]);
});
