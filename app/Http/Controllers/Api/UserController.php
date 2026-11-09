<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    public function index(Request $request)
    {
        $result = $this->service->list(
            $request->user()->company_id,
            $request->only(['branch_id','role','is_active','search'])
        );
        return response()->json($result, $result['code']);
    }

    public function create()
    {
        return response()->json([
            'success' => true,
            'message' => 'نموذج إنشاء مستخدم',
            'data'    => [
                'roles' => [
                    ['value' => 'admin',       'label' => 'مدير النظام'],
                    ['value' => 'manager',     'label' => 'مدير'],
                    ['value' => 'cashier',     'label' => 'كاشير'],
                    ['value' => 'accountant',  'label' => 'محاسب'],
                    ['value' => 'storekeeper', 'label' => 'مدير مخزون'],
                    ['value' => 'viewer',      'label' => 'مشاهد'],
                ],
            ],
            'code' => 200,
        ], 200);
    }

    public function show(Request $request, int $id)
    {
        $result = $this->service->getById($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function edit(Request $request, int $id)
    {
        return $this->show($request, $id);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'mobile'              => 'required|string|max:30',
            'password'            => 'required|string|min:6',
            'email'               => 'nullable|email|max:150',
            'role'                => 'nullable|in:admin,manager,cashier,accountant,storekeeper,viewer',
            'branch_id'           => 'required|integer',
            'branch_ids'          => 'nullable|array',
            'branch_ids.*'        => 'integer',
            'avatar_path'         => 'nullable|string|max:300',
            'allow_branch_switch' => 'nullable|integer|in:0,1',
            'is_active'           => 'nullable|integer|in:0,1',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $data['user_id']    = $request->user()->id;

        $result = $this->service->create($data);
        return response()->json($result, $result['code']);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name'                => 'sometimes|string|max:150',
            'mobile'              => 'sometimes|string|max:30',
            'email'               => 'nullable|email|max:150',
            'role'                => 'sometimes|in:admin,manager,cashier,accountant,storekeeper,viewer',
            'branch_id'           => 'sometimes|integer',
            'branch_ids'          => 'nullable|array',
            'branch_ids.*'        => 'integer',
            'avatar_path'         => 'nullable|string|max:300',
            'allow_branch_switch' => 'nullable|integer|in:0,1',
            'is_active'           => 'nullable|integer|in:0,1',
        ]);
        $data['user_id'] = $request->user()->id;

        $result = $this->service->update($id, $request->user()->company_id, $data);
        return response()->json($result, $result['code']);
    }

    public function destroy(Request $request, int $id)
    {
        $result = $this->service->delete($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function toggleActive(Request $request, int $id)
    {
        $result = $this->service->toggleActive($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function changePassword(Request $request, int $id)
    {
        $data = $request->validate([
            'old_password' => 'nullable|string',
            'new_password' => 'required|string|min:6',
        ]);

        $isAdmin = in_array($request->user()->role, ['admin','manager']);
        $result = $this->service->changePassword(
            $id,
            $request->user()->company_id,
            $data['new_password'],
            $data['old_password'] ?? null,
            $isAdmin
        );
        return response()->json($result, $result['code']);
    }
}
