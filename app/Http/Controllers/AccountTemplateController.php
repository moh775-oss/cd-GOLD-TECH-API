<?php

namespace App\Http\Controllers;

use App\Models\AccountTemplate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class AccountTemplateController extends Controller
{
    public function index(): View
    {
        try {
            if (!Schema::hasTable('accounts_template')) {
                return view('accounts-template', [
                    'tableExists' => false,
                    'accounts' => collect(),
                    'error' => null,
                ]);
            }

            $accounts = AccountTemplate::orderBy('sort_order')->get();

            return view('accounts-template', [
                'tableExists' => true,
                'accounts' => $accounts,
                'error' => null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return view('accounts-template', [
                'tableExists' => null,
                'accounts' => collect(),
                'error' => 'تعذر الاتصال بقاعدة البيانات أو قراءة الجدول.',
            ]);
        }
    }
}
