<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class UserController extends Controller
{
    private array $allowedRoles = ['admin', 'accountant', 'cashier'];

    private array $roleNameMap = [
        'admin'      => 'مدير',
        'accountant' => 'محاسب',
        'cashier'    => 'كاشير',
    ];

    public function index(Request $request)
    {
        try {
            $q = User::with(['branch', 'roles'])
                ->where('company_id', $request->user()->company_id);

            if ($request->filled('branch_id')) $q->where('branch_id', $request->branch_id);
            if ($request->filled('role')) $q->where('role', $request->role);
            if ($request->filled('is_active')) $q->where('is_active', $request->is_active);
            if ($request->filled('search')) {
                $s = $request->search;
                $q->where(function ($w) use ($s) {
                    $w->where('name', 'LIKE', "%{$s}%")
                      ->orWhere('mobile', 'LIKE', "%{$s}%")
                      ->orWhere('email', 'LIKE', "%{$s}%");
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => $q->orderBy('name')->get(),
                'code'    => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function show(Request $request, int $id)
    {
        try {
            $user = User::with(['branch', 'roles', 'branches'])
                ->where('company_id', $request->user()->company_id)
                ->find($id);

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'المستخدم غير موجود', 'data' => null, 'code' => 404], 404);
            }

            return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $user, 'code' => 200], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function create(Request $request)
    {
        try {
            $companyId = $request->user()->company_id;

            return response()->json([
                'success' => true,
                'message' => 'نموذج إنشاء مستخدم',
                'data'    => [
                    'branches' => Branch::where('company_id', $companyId)
                        ->where('is_active', 1)
                        ->orderBy('name')
                        ->get(['id', 'name']),
                    'roles' => Role::where('is_active', 1)
                        ->orderBy('name')
                        ->get(['id', 'name']),
                    'basic_roles' => [
                        ['value' => 'admin',      'label' => 'مدير'],
                        ['value' => 'accountant', 'label' => 'محاسب'],
                        ['value' => 'cashier',    'label' => 'كاشير'],
                    ],
                ],
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'mobile'              => 'required|string|max:30',
            'password'            => 'required|string|min:6',
            'email'               => 'nullable|email|max:150',
            'role'                => 'nullable|string|max:50',
            'branch_id'           => 'required|integer',
            'branch_ids'          => 'nullable|array',
            'branch_ids.*'        => 'integer',
            'role_ids'            => 'nullable|array',
            'role_ids.*'          => 'integer',
            'allow_branch_switch' => 'nullable|integer|in:0,1',
            'is_active'           => 'nullable|integer|in:0,1',
        ]);

        $role = $data['role'] ?? 'cashier';
        if (!in_array($role, $this->allowedRoles)) {
            return response()->json(['success' => false, 'message' => 'الدور غير صحيح', 'data' => null, 'code' => 422], 422);
        }

        $companyId = $request->user()->company_id;

        if (User::where('mobile', $data['mobile'])->exists()) {
            return response()->json(['success' => false, 'message' => 'رقم الهاتف مستخدم مسبقاً', 'data' => null, 'code' => 409], 409);
        }

        $branch = Branch::where('id', $data['branch_id'])->where('company_id', $companyId)->first();
        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'الفرع غير صالح', 'data' => null, 'code' => 422], 422);
        }

        // ✅ ابحث عن الدور المطابق مسبقاً
        $roleName = $this->roleNameMap[$role] ?? null;
        $roleObj  = $roleName ? Role::where('name', $roleName)->first() : null;

        return DB::transaction(function () use ($data, $companyId, $branch, $role, $roleObj, $request) {

            $user = User::create([
                'company_id'          => $companyId,
                'branch_id'           => $branch->id,
                'name'                => $data['name'],
                'mobile'              => $data['mobile'],
                'password_hash'       => Hash::make($data['password']),
                'email'               => $data['email'] ?? null,
                'role'                => $role,
                'allow_branch_switch' => $data['allow_branch_switch'] ?? 0,
                'is_active'           => $data['is_active'] ?? 1,
                'user_id'             => $request->user()->id,
            ]);

            // ربط الفروع
            $branchIds = $data['branch_ids'] ?? [$branch->id];
            if (!is_array($branchIds)) $branchIds = [$branch->id];

            $validBranches = Branch::where('company_id', $companyId)
                ->whereIn('id', $branchIds)
                ->pluck('id')
                ->all();

            $rows = [];
            foreach ($validBranches as $bid) {
                $rows[] = [
                    'company_id' => $companyId,
                    'user_id'    => $user->id,
                    'branch_id'  => $bid,
                    'is_default' => $bid === $branch->id ? 1 : 0,
                ];
            }
            if (!empty($rows)) {
                DB::table('user_branches')->insert($rows);
            }

            // ✅ ربط الدور الأساسي تلقائياً
            $roleIdsToAssign = [];

            if ($roleObj) {
                $roleIdsToAssign[] = $roleObj->id;
            }

            // إضافة الأدوار الإضافية إن وُجدت
            if (!empty($data['role_ids']) && is_array($data['role_ids'])) {
                $extraRoles = Role::whereIn('id', $data['role_ids'])->pluck('id')->all();
                $roleIdsToAssign = array_unique(array_merge($roleIdsToAssign, $extraRoles));
            }

            if (!empty($roleIdsToAssign)) {
                $roleRows = [];
                foreach ($roleIdsToAssign as $rid) {
                    $roleRows[] = [
                        'company_id' => $companyId,
                        'user_id'    => $user->id,
                        'role_id'    => $rid,
                    ];
                }
                DB::table('user_roles')->insert($roleRows);
            }

            return response()->json(['success' => true, 'message' => 'تم إنشاء المستخدم', 'data' => $user, 'code' => 201], 201);
        });
    }

    public function edit(Request $request, int $id)
    {
        try {
            $user = User::with(['branch', 'roles', 'branches'])
                ->where('company_id', $request->user()->company_id)
                ->find($id);

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'المستخدم غير موجود', 'data' => null, 'code' => 404], 404);
            }

            $companyId = $request->user()->company_id;

            return response()->json([
                'success' => true,
                'message' => 'بيانات تعديل المستخدم',
                'data'    => [
                    'user' => $user,
                    'branches' => Branch::where('company_id', $companyId)
                        ->where('is_active', 1)
                        ->orderBy('name')
                        ->get(['id', 'name']),
                    'roles' => Role::where('is_active', 1)
                        ->orderBy('name')
                        ->get(['id', 'name']),
                    'basic_roles' => [
                        ['value' => 'admin',      'label' => 'مدير'],
                        ['value' => 'accountant', 'label' => 'محاسب'],
                        ['value' => 'cashier',    'label' => 'كاشير'],
                    ],
                ],
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $user = User::where('company_id', $request->user()->company_id)->find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود', 'data' => null, 'code' => 404], 404);
        }

        $data = $request->validate([
            'name'                => 'sometimes|string|max:150',
            'mobile'              => 'sometimes|string|max:30',
            'password'            => 'nullable|string|min:6',
            'email'               => 'nullable|email|max:150',
            'role'                => 'sometimes|string|max:50',
            'branch_id'           => 'sometimes|integer',
            'branch_ids'          => 'nullable|array',
            'branch_ids.*'        => 'integer',
            'role_ids'            => 'nullable|array',
            'role_ids.*'          => 'integer',
            'allow_branch_switch' => 'nullable|integer|in:0,1',
            'is_active'           => 'sometimes|integer|in:0,1',
        ]);

        if (isset($data['role']) && !in_array($data['role'], $this->allowedRoles)) {
            return response()->json(['success' => false, 'message' => 'الدور غير صحيح', 'data' => null, 'code' => 422], 422);
        }

        $companyId = $request->user()->company_id;

        if (isset($data['mobile']) && $data['mobile'] !== $user->mobile) {
            if (User::where('mobile', $data['mobile'])->where('id', '!=', $id)->exists()) {
                return response()->json(['success' => false, 'message' => 'رقم الهاتف مستخدم مسبقاً', 'data' => null, 'code' => 409], 409);
            }
        }

        // ✅ الدور الحالي/الجديد
        $newRole     = $data['role'] ?? $user->role;
        $roleName    = $this->roleNameMap[$newRole] ?? null;
        $roleObj     = $roleName ? Role::where('name', $roleName)->first() : null;

        return DB::transaction(function () use ($data, $companyId, $user, $roleObj, $request) {

            $update = [
                'name'                => $data['name']                ?? $user->name,
                'mobile'              => $data['mobile']              ?? $user->mobile,
                'email'               => $data['email']               ?? $user->email,
                'role'                => $data['role']                ?? $user->role,
                'branch_id'           => $data['branch_id']           ?? $user->branch_id,
                'allow_branch_switch' => $data['allow_branch_switch'] ?? $user->allow_branch_switch,
                'is_active'           => $data['is_active']           ?? $user->is_active,
                'user_id'             => $request->user()->id,
            ];

            if (!empty($data['password'])) {
                $update['password_hash'] = Hash::make($data['password']);
            }

            $user->update($update);

            // تحديث الفروع
            if (isset($data['branch_ids']) && is_array($data['branch_ids'])) {
                DB::table('user_branches')->where('user_id', $user->id)->delete();

                $validBranches = Branch::where('company_id', $companyId)
                    ->whereIn('id', $data['branch_ids'])
                    ->pluck('id')
                    ->all();

                $rows = [];
                foreach ($validBranches as $bid) {
                    $rows[] = [
                        'company_id' => $companyId,
                        'user_id'    => $user->id,
                        'branch_id'  => $bid,
                        'is_default' => $bid === $user->branch_id ? 1 : 0,
                    ];
                }
                if (!empty($rows)) {
                    DB::table('user_branches')->insert($rows);
                }
            }

            // ✅ تحديث الأدوار: الأساسي + الإضافية
            $roleIdsToAssign = [];

            if ($roleObj) {
                $roleIdsToAssign[] = $roleObj->id;
            }

            if (isset($data['role_ids']) && is_array($data['role_ids'])) {
                $extraRoles = Role::whereIn('id', $data['role_ids'])->pluck('id')->all();
                $roleIdsToAssign = array_unique(array_merge($roleIdsToAssign, $extraRoles));
            }

            DB::table('user_roles')->where('user_id', $user->id)->delete();

            if (!empty($roleIdsToAssign)) {
                $roleRows = [];
                foreach ($roleIdsToAssign as $rid) {
                    $roleRows[] = [
                        'company_id' => $companyId,
                        'user_id'    => $user->id,
                        'role_id'    => $rid,
                    ];
                }
                DB::table('user_roles')->insert($roleRows);
            }

            return response()->json(['success' => true, 'message' => 'تم تعديل المستخدم', 'data' => $user, 'code' => 200], 200);
        });
    }

    public function destroy(Request $request, int $id)
    {
        $user = User::where('company_id', $request->user()->company_id)->find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود', 'data' => null, 'code' => 404], 404);
        }

        if ($user->id === 1) {
            return response()->json(['success' => false, 'message' => 'لا يمكن حذف مدير النظام الرئيسي', 'data' => null, 'code' => 409], 409);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'لا يمكنك حذف حسابك الحالي', 'data' => null, 'code' => 409], 409);
        }

        if ($user->role === 'admin') {
            $adminCount = User::where('company_id', $user->company_id)->where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json(['success' => false, 'message' => 'لا يمكن حذف آخر مدير في الشركة', 'data' => null, 'code' => 409], 409);
            }
        }

        return DB::transaction(function () use ($user) {
            DB::table('user_branches')->where('user_id', $user->id)->delete();
            DB::table('user_roles')->where('user_id', $user->id)->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', User::class)
                ->delete();
            $user->delete();

            return response()->json(['success' => true, 'message' => 'تم حذف المستخدم', 'data' => null, 'code' => 200], 200);
        });
    }

    public function toggleActive(Request $request, int $id)
    {
        $user = User::where('company_id', $request->user()->company_id)->find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود', 'data' => null, 'code' => 404], 404);
        }
        if ($user->id === 1) {
            return response()->json(['success' => false, 'message' => 'لا يمكن تعطيل مدير النظام الرئيسي', 'data' => null, 'code' => 409], 409);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'لا يمكنك تعطيل حسابك الحالي', 'data' => null, 'code' => 409], 409);
        }

        $user->update(['is_active' => $user->is_active ? 0 : 1]);

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'تم تفعيل المستخدم' : 'تم تعطيل المستخدم',
            'data'    => ['is_active' => $user->is_active],
            'code'    => 200,
        ], 200);
    }

    public function changePassword(Request $request, int $id)
    {
        $data = $request->validate([
            'old_password' => 'nullable|string',
            'new_password' => 'required|string|min:6',
        ]);

        $user = User::where('company_id', $request->user()->company_id)->find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود', 'data' => null, 'code' => 404], 404);
        }

        $isAdmin = in_array($request->user()->role, ['admin']);

        if (!$isAdmin) {
            if (empty($data['old_password']) || !Hash::check($data['old_password'], $user->password_hash)) {
                return response()->json(['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة', 'data' => null, 'code' => 401], 401);
            }
        }

        $user->update(['password_hash' => Hash::make($data['new_password'])]);

        DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', User::class)
            ->delete();

        return response()->json(['success' => true, 'message' => 'تم تغيير كلمة المرور', 'data' => null, 'code' => 200], 200);
    }
}
