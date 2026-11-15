<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $permissions = Permission::orderBy('module')->orderBy('name')->get();
            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => $permissions,
                'code'    => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function tree(Request $request)
    {
        try {
            $permissions = Permission::orderBy('module')->orderBy('name')->get();

            $grouped = [];
            foreach ($permissions as $p) {
                $module = $p->module ?: 'other';
                if (!isset($grouped[$module])) {
                    $grouped[$module] = [
                        'key'      => 'module_' . $module,
                        'title'    => $this->moduleLabel($module),
                        'isParent' => true,
                        'children' => [],
                    ];
                }
                $grouped[$module]['children'][] = [
                    'key'          => 'perm_' . $p->id,
                    'title'        => $p->name,
                    'permissionId' => $p->id,
                    'code'         => $p->code,
                    'module'       => $module,
                    'isParent'     => false,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => array_values($grouped),
                'code'    => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function getUserPermissions(Request $request, int $userId)
    {
        try {
            $target = User::where('company_id', $request->user()->company_id)->find($userId);
            if (!$target) {
                return response()->json(['success' => false, 'message' => 'المستخدم غير موجود', 'data' => null, 'code' => 404], 404);
            }

            $userPerms = DB::table('user_roles')
                ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $userId)
                ->where('user_roles.company_id', $target->company_id)
                ->pluck('role_permissions.permission_id')
                ->unique()
                ->values()
                ->all();

            $userRoles = DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('company_id', $target->company_id)
                ->pluck('role_id')
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => [
                    'user'                => $target,
                    'user_permission_ids' => $userPerms,
                    'user_role_ids'       => $userRoles,
                ],
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function saveUserPermissions(Request $request, int $userId)
    {
        $data = $request->validate([
            'permission_ids'   => 'nullable|array',
            'permission_ids.*' => 'integer',
        ]);

        try {
            $target = User::where('company_id', $request->user()->company_id)->find($userId);
            if (!$target) {
                return response()->json(['success' => false, 'message' => 'المستخدم غير موجود', 'data' => null, 'code' => 404], 404);
            }

            // ✅ $request مُمرَّر إلى الـ closure
            return DB::transaction(function () use ($target, $data, $request) {

                $customRoleName = 'مخصص_' . $target->id;

                $customRole = Role::where('name', $customRoleName)->first();
                if (!$customRole) {
                    $customRole = Role::create([
                        'name'        => $customRoleName,
                        'description' => 'دور مخصص للمستخدم ' . $target->name,
                        'is_system'   => 0,
                        'is_active'   => 1,
                        'user_id'     => $request->user()->id,
                    ]);
                }

                DB::table('role_permissions')->where('role_id', $customRole->id)->delete();

                if (!empty($data['permission_ids'])) {
                    $validPerms = Permission::whereIn('id', $data['permission_ids'])->pluck('id')->all();

                    $rows = [];
                    foreach ($validPerms as $pid) {
                        $rows[] = [
                            'role_id'       => $customRole->id,
                            'permission_id' => $pid,
                        ];
                    }
                    if (!empty($rows)) {
                        DB::table('role_permissions')->insert($rows);
                    }
                }

                DB::table('user_roles')->where('user_id', $target->id)->delete();

                DB::table('user_roles')->insert([
                    'company_id' => $target->company_id,
                    'user_id'    => $target->id,
                    'role_id'    => $customRole->id,
                ]);

                DB::table('personal_access_tokens')
                    ->where('tokenable_id', $target->id)
                    ->where('tokenable_type', User::class)
                    ->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'تم حفظ الصلاحيات',
                    'data'    => null,
                    'code'    => 200,
                ], 200);
            });
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    private function moduleLabel(string $module): string
    {
        $labels = [
            'companies'           => 'الشركات',
            'branches'            => 'الفروع',
            'users'               => 'المستخدمون',
            'roles'               => 'الأدوار',
            'accounts'            => 'الحسابات',
            'journal'             => 'القيود',
            'cost_centers'        => 'مراكز التكلفة',
            'contacts'            => 'العملاء والموردون',
            'items'               => 'الأصناف',
            'item_categories'     => 'مجموعات الأصناف',
            'karats'              => 'العيارات',
            'taxes'               => 'الضرائب',
            'currencies'          => 'العملات',
            'exchange_rates'      => 'أسعار الصرف',
            'stores'              => 'المخازن',
            'warehouse_stock'     => 'أرصدة المخزون',
            'stock_movements'     => 'حركات المخزون',
            'safes'               => 'الصناديق',
            'banks'               => 'البنوك',
            'sales_net'           => 'مبيعات صافي',
            'sales_jewelry'       => 'مبيعات مصاغ',
            'sales_closing'       => 'مبيعات تسكير',
            'sales_wages'         => 'مبيعات أجور',
            'sales_return_net'    => 'مرتجع صافي',
            'sales_return_jewelry'=> 'مرتجع مصاغ',
            'sales_return_wages'  => 'مرتجع أجور',
            'sales_return_closing'=> 'مرتجع تسكير',
            'purchase_net'        => 'مشتريات صافي',
            'purchase_jewelry'    => 'مشتريات مصاغ',
            'purchase_closing'    => 'مشتريات تسكير',
            'purchase_wages'      => 'مشتريات أجور',
            'purchase_return_net' => 'مرتجع مشتريات صافي',
            'purchase_return_jewelry' => 'مرتجع مشتريات مصاغ',
            'purchase_return_wages'   => 'مرتجع مشتريات أجور',
            'purchase_return_closing' => 'مرتجع مشتريات تسكير',
            'receipt_vouchers'    => 'سندات القبض',
            'payment_vouchers'    => 'سندات الصرف',
            'expenses'            => 'المصروفات',
            'expense_categories'  => 'تصنيفات المصروفات',
            'gold_prices'         => 'أسعار الذهب',
            'reports'             => 'التقارير',
            'settings'            => 'الإعدادات',
            'company_info'        => 'بيانات الشركة',
            'document_types'      => 'أنواع المستندات',
        ];
        return $labels[$module] ?? $module;
    }
}
