<?php

namespace App\Http\Controllers\Api;



use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        try {
            $q = Role::with(['permissions']);

            if ($request->filled('is_active')) $q->where('is_active', $request->is_active);
            if ($request->filled('search')) $q->where('name', 'LIKE', "%{$request->search}%");

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
            $role = Role::with(['permissions'])->find($id);

            if (!$role) {
                return response()->json(['success' => false, 'message' => 'الدور غير موجود', 'data' => null, 'code' => 404], 404);
            }

            return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $role, 'code' => 200], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function create(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'نموذج إنشاء دور',
                'data'    => [
                    'permissions' => Permission::orderBy('module')->orderBy('name')->get(['id', 'code', 'name', 'module']),
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
            'name'             => 'required|string|max:100',
            'description'      => 'nullable|string|max:300',
            'permission_ids'   => 'nullable|array',
            'permission_ids.*' => 'integer',
            'is_active'        => 'nullable|integer|in:0,1',
        ]);

        if (Role::where('name', $data['name'])->exists()) {
            return response()->json(['success' => false, 'message' => 'اسم الدور مستخدم مسبقاً', 'data' => null, 'code' => 409], 409);
        }

        return DB::transaction(function () use ($data, $request) {

            $role = Role::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'is_system'   => 0,
                'is_active'   => $data['is_active'] ?? 1,
                'user_id'     => $request->user()->id,
            ]);

            if (!empty($data['permission_ids'])) {
                $validPerms = Permission::whereIn('id', $data['permission_ids'])->pluck('id')->all();

                $rows = [];
                foreach ($validPerms as $pid) {
                    $rows[] = [
                        'role_id'       => $role->id,
                        'permission_id' => $pid,
                    ];
                }
                if (!empty($rows)) {
                    DB::table('role_permissions')->insert($rows);
                }
            }

            return response()->json(['success' => true, 'message' => 'تم إنشاء الدور', 'data' => $role, 'code' => 201], 201);
        });
    }

    public function edit(Request $request, int $id)
    {
        return $this->show($request, $id);
    }

    public function update(Request $request, int $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['success' => false, 'message' => 'الدور غير موجود', 'data' => null, 'code' => 404], 404);
        }

        if ($role->is_system && $role->name === 'مدير') {
            return response()->json(['success' => false, 'message' => 'لا يمكن تعديل دور المدير', 'data' => null, 'code' => 409], 409);
        }

        $data = $request->validate([
            'name'             => 'sometimes|string|max:100',
            'description'      => 'nullable|string|max:300',
            'permission_ids'   => 'nullable|array',
            'permission_ids.*' => 'integer',
            'is_active'        => 'sometimes|integer|in:0,1',
        ]);

        if (isset($data['name']) && Role::where('name', $data['name'])->where('id', '!=', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'اسم الدور مستخدم مسبقاً', 'data' => null, 'code' => 409], 409);
        }

        return DB::transaction(function () use ($data, $role, $request) {

            $role->update([
                'name'        => $data['name']        ?? $role->name,
                'description' => $data['description'] ?? $role->description,
                'is_active'   => $data['is_active']   ?? $role->is_active,
                'user_id'     => $request->user()->id,
            ]);

            if (isset($data['permission_ids']) && is_array($data['permission_ids'])) {
                DB::table('role_permissions')->where('role_id', $role->id)->delete();

                $validPerms = Permission::whereIn('id', $data['permission_ids'])->pluck('id')->all();

                $rows = [];
                foreach ($validPerms as $pid) {
                    $rows[] = [
                        'role_id'       => $role->id,
                        'permission_id' => $pid,
                    ];
                }
                if (!empty($rows)) {
                    DB::table('role_permissions')->insert($rows);
                }
            }

            return response()->json(['success' => true, 'message' => 'تم تعديل الدور', 'data' => $role, 'code' => 200], 200);
        });
    }

    public function destroy(Request $request, int $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['success' => false, 'message' => 'الدور غير موجود', 'data' => null, 'code' => 404], 404);
        }

        if ($role->is_system) {
            return response()->json(['success' => false, 'message' => 'لا يمكن حذف دور نظامي', 'data' => null, 'code' => 409], 409);
        }

        if (DB::table('user_roles')->where('role_id', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'لا يمكن حذف دور مستخدم من قبل مستخدمين', 'data' => null, 'code' => 409], 409);
        }

        return DB::transaction(function () use ($role) {
            DB::table('role_permissions')->where('role_id', $role->id)->delete();
            $role->delete();

            return response()->json(['success' => true, 'message' => 'تم حذف الدور', 'data' => null, 'code' => 200], 200);
        });
    }
}
