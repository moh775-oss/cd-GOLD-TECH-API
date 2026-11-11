<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MasterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(private MasterService $master) {}

    public function index(Request $request)
    {
        $result = $this->master->listAccounts(
            $request->user()->company_id,
            $request->only(['branch_id','account_type','account_nature','final_account','is_active','search'])
        );
        return response()->json($result, $result['code']);
    }

    public function tree(Request $request)
    {
        $result = $this->master->getAccountsTree($request->user()->company_id, $request->branch_id);
        return response()->json($result, $result['code']);
    }

    public function show(Request $request, int $id)
    {
        $result = $this->master->getAccountById($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function byAccNo(Request $request, string $accNo)
    {
        $result = $this->master->getAccountByAccNo($accNo, $request->user()->company_id, $request->branch_id);
        return response()->json($result, $result['code']);
    }

    public function byName(Request $request, string $name)
    {
        $result = $this->master->getAccountsByName($name, $request->user()->company_id, $request->branch_id);
        return response()->json($result, $result['code']);
    }

    public function create(Request $request)
    {
        // ✅ لف في transaction عشان generateSubAccountNo تشتغل
        return DB::transaction(function () use ($request) {
            $result = $this->master->generateSubAccountNo(
                $request->user()->company_id,
                $request->branch_id,
                (string)$request->father_acc_no
            );
            return response()->json($result, $result['code']);
        });
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id'      => 'required|integer',
            'acc_no'         => 'required|string|max:50',
            'name'           => 'required|string|max:200',
            'father_acc_no'  => 'nullable|string|max:50',
            'account_nature' => 'nullable|in:debit,credit',
            'final_account'  => 'nullable|in:الميزانية العمومية,الأرباح والخسائر',
            'is_active'      => 'nullable|integer|in:0,1',
            'notes'          => 'nullable|string|max:500',
        ]);
        $data['company_id'] = $request->user()->company_id;
        $data['user_id']    = $request->user()->id;
        $result = $this->master->createAccount($data);
        return response()->json($result, $result['code']);
    }

    public function edit(Request $request, int $id)
    {
        $result = $this->master->getAccountById($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'acc_no'         => 'sometimes|string|max:50',
            'name'           => 'sometimes|string|max:200',
            'father_acc_no'  => 'sometimes|nullable|string|max:50',
            'account_nature' => 'sometimes|in:debit,credit',
            'final_account'  => 'sometimes|in:الميزانية العمومية,الأرباح والخسائر',
            'is_active'      => 'sometimes|integer|in:0,1',
            'notes'          => 'nullable|string|max:500',
        ]);
        $data['user_id'] = $request->user()->id;
        $result = $this->master->updateAccount($id, $request->user()->company_id, $data);
        return response()->json($result, $result['code']);
    }

    public function destroy(Request $request, int $id)
    {
        $result = $this->master->deleteAccount($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }
}
