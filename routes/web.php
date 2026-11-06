<?php

use App\Http\Controllers\AccountTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/accounts-template', [AccountTemplateController::class, 'index']);
