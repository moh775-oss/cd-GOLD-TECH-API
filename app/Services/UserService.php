<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Concerns\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserService
{
    use ApiResponse;

    public function __construct(private MasterService $master) {}

    // ============================================================
    //                        إنشاء مستخدم
    // ============================================================

    /**
     * إنشاء مستخدم جديد داخل الشركة (مع ربطه بالفروع)
     */
    public function create(array $data): array
    {
        try {
            $ctx = $this->master->validateContext(
                (int)$data['company_id'],
                (int)$data['branch_id'],
                $data['user_id'] ?? Auth::id()
            );
            if (!$ctx['success']) return $ctx;

            // 1. الحقول الإلزامية
            foreach (['name','mobile','password','branch_id'] as $f) {
                if (empty($data[$f])) {
                    return $this->fail("الحقل {$f} مطلوب", null, 422);
                }
            }

            // 2. صحة الهاتف
            if (!preg_match('/^05\d{8}$/', $data['mobile']) && !preg_match('/^\d{9,15}$/', $data['mobile'])) {
                return $this->fail('رقم الهاتف غير صحيح', null, 422);
            }

            // 3. تكرار الهاتف على مستوى النظام
            if (User::where('mobile', $data['mobile'])->exists()) {
                return $this->fail('رقم الهاتف مستخدم مسبقاً', null, 409);
            }

            // 4. كلمة المرور
            if (strlen($data['password']) < 6) {
                return $this->fail('كلمة المرور يجب أن تكون 6 أحرف على الأقل', null, 422);
            }

            // 5. حدود الشركة
            $company = Company::find($data['company_id']);
            if ($company->max_users > 0) {
                $currentCount = User::where('company_id', $company->id)->count();
                if ($currentCount >= $company->max_users) {
                    return $this->fail('وصلت الشركة للحد الأقصى من المستخدمين (' . $company->max_users . ')', null, 403);
                }
            }

            // 6. الفرع الرئيسي موجود ويخص نفس الشركة
            $mainBranch = Branch::where('id', $data['branch_id'])
                ->where('company_id', $company->id)
                ->first();
            if (!$mainBranch) {
                return $this->fail('الفرع غير صالح', null, 422);
            }

            // 7. الأدوار المسموحة
            $allowedRoles = ['admin','manager','cashier','accountant','storekeeper','viewer'];
            $role = $data['role'] ?? 'cashier';
            if (!in_array($role, $allowedRoles)) {
                return $this->fail('الدور غير صحيح', null, 422);
            }

            return DB::transaction(function () use ($data, $company, $mainBranch, $role) {

                // 8. إنشاء المستخدم
                $user = User::create([
                    'company_id'          => $company->id,
                    'branch_id'           => $mainBranch->id,
                    'name'                => $data['name'],
                    'mobile'              => $data['mobile'],
                    'password_hash'       => Hash::make($data['password']),
                    'email'               => $data['email']               ?? null,
                    'role'                => $role,
                    'avatar_path'         => $data['avatar_path']         ?? null,
                    'allow_branch_switch' => $data['allow_branch_switch'] ?? 0,
                    'is_active'           => $data['is_active']           ?? 1,
                    'user_id'             => $data['user_id']             ?? Auth::id(),
                ]);

                // 9. ربط المستخدم بالفروع
                $branchIds = $data['branch_ids'] ?? [$mainBranch->id];
                if (!is_array($branchIds)) $branchIds = [$branchIds];

                // تأكد أن كل فرع يخص نفس الشركة
                $validBranches = Branch::where('company_id', $company->id)
                    ->whereIn('id', $branchIds)
                    ->pluck('id')->all();

                $rows = [];
                foreach ($validBranches as $bid) {
                    $rows[] = [
                        'company_id' => $company->id,
                        'user_id'    => $user->id,
                        'branch_id'  => $bid,
                        'is_default' => $bid === $mainBranch->id ? 1 : 0,
                        'created_at' => now(),
                    ];
                }
                if (!empty($rows)) {
                    DB::table('user_branches')->insert($rows);
                }

                // 10. تحديث عداد المستخدمين
                $count = User::where('company_id', $company->id)->count();
                $company->update(['current_users_count' => $count]);

                return $this->ok('تم إنشاء المستخدم بنجاح', [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'mobile'       => $user->mobile,
                    'role'         => $user->role,
                    'branch_id'    => $user->branch_id,
                    'branch_ids'   => $validBranches,
                ], 201);
            });
        } catch (Throwable $e) {
            Log::error('UserService@create: ' . $e->getMessage());
            return $this->fail('فشل إنشاء المستخدم: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        تعديل مستخدم
    // ============================================================

    public function update(int $userId, int $companyId, array $data): array
    {
        try {
            $user = User::where('id', $userId)->where('company_id', $companyId)->first();
            if (!$user) return $this->fail('المستخدم غير موجود', null, 404);

            $ctx = $this->master->validateContext(
                $companyId,
                (int)($data['branch_id'] ?? $user->branch_id),
                $data['user_id'] ?? Auth::id()
            );
            if (!$ctx['success']) return $ctx;

            // منع تعديل مدير النظام من قبل مستخدم آخر (اختياري)
            if ($user->role === 'admin' && ($data['user_id'] ?? Auth::id()) !== $user->id) {
                return $this->fail('لا يمكن تعديل مدير النظام من قبل مستخدم آخر', null, 403);
            }

            // الهاتف: منع التكرار عند تغييره
            if (!empty($data['mobile']) && $data['mobile'] !== $user->mobile) {
                if (User::where('mobile', $data['mobile'])->where('id', '!=', $userId)->exists()) {
                    return $this->fail('رقم الهاتف مستخدم مسبقاً', null, 409);
                }
            }

            // الدور
            if (isset($data['role'])) {
                $allowedRoles = ['admin','manager','cashier','accountant','storekeeper','viewer'];
                if (!in_array($data['role'], $allowedRoles)) {
                    return $this->fail('الدور غير صحيح', null, 422);
                }
            }

            $user->update([
                'name'                => $data['name']                ?? $user->name,
                'mobile'              => $data['mobile']              ?? $user->mobile,
                'email'               => $data['email']               ?? $user->email,
                'role'                => $data['role']                ?? $user->role,
                'branch_id'           => $data['branch_id']           ?? $user->branch_id,
                'avatar_path'         => $data['avatar_path']         ?? $user->avatar_path,
                'allow_branch_switch' => $data['allow_branch_switch'] ?? $user->allow_branch_switch,
                'is_active'           => $data['is_active']           ?? $user->is_active,
                'user_id'             => $data['user_id']             ?? Auth::id(),
            ]);

            // تحديث الفروع إن مُرّرت
            if (isset($data['branch_ids']) && is_array($data['branch_ids'])) {
                DB::table('user_branches')->where('user_id', $user->id)->delete();

                $validBranches = Branch::where('company_id', $companyId)
                    ->whereIn('id', $data['branch_ids'])
                    ->pluck('id')->all();

                $rows = [];
                foreach ($validBranches as $bid) {
                    $rows[] = [
                        'company_id' => $companyId,
                        'user_id'    => $user->id,
                        'branch_id'  => $bid,
                        'is_default' => $bid === $user->branch_id ? 1 : 0,
                        'created_at' => now(),
                    ];
                }
                if (!empty($rows)) {
                    DB::table('user_branches')->insert($rows);
                }
            }

            return $this->ok('تم تعديل المستخدم بنجاح', $user);
        } catch (Throwable $e) {
            Log::error('UserService@update: ' . $e->getMessage());
            return $this->fail('فشل تعديل المستخدم: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        تغيير كلمة المرور
    // ============================================================

    public function changePassword(int $userId, int $companyId, string $newPassword, ?string $oldPassword = null, bool $isAdmin = false): array
    {
        try {
            $user = User::where('id', $userId)->where('company_id', $companyId)->first();
            if (!$user) return $this->fail('المستخدم غير موجود', null, 404);

            if (strlen($newPassword) < 6) {
                return $this->fail('كلمة المرور يجب أن تكون 6 أحرف على الأقل', null, 422);
            }

            // إن لم يكن مديراً، تحقق من كلمة المرور القديمة
            if (!$isAdmin) {
                if (empty($oldPassword) || !Hash::check($oldPassword, $user->password_hash)) {
                    return $this->fail('كلمة المرور الحالية غير صحيحة', null, 401);
                }
            }

            $user->update(['password_hash' => Hash::make($newPassword)]);

            // إبطال كل التوكنات
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', User::class)
                ->delete();

            return $this->ok('تم تغيير كلمة المرور بنجاح');
        } catch (Throwable $e) {
            Log::error('UserService@changePassword: ' . $e->getMessage());
            return $this->fail('فشل تغيير كلمة المرور: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        تفعيل / تعطيل
    // ============================================================

    public function toggleActive(int $userId, int $companyId): array
    {
        try {
            $user = User::where('id', $userId)->where('company_id', $companyId)->first();
            if (!$user) return $this->fail('المستخدم غير موجود', null, 404);

            if ($user->id === Auth::id()) {
                return $this->fail('لا يمكنك تعطيل حسابك الحالي', null, 409);
            }

            $user->update(['is_active' => $user->is_active ? 0 : 1]);

            return $this->ok($user->is_active ? 'تم تفعيل المستخدم' : 'تم تعطيل المستخدم', ['is_active' => $user->is_active]);
        } catch (Throwable $e) {
            Log::error('UserService@toggleActive: ' . $e->getMessage());
            return $this->fail('فشل تغيير الحالة: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        حذف
    // ============================================================

    public function delete(int $userId, int $companyId): array
    {
        try {
            $user = User::where('id', $userId)->where('company_id', $companyId)->first();
            if (!$user) return $this->fail('المستخدم غير موجود', null, 404);

            if ($user->id === Auth::id()) {
                return $this->fail('لا يمكنك حذف حسابك الحالي', null, 409);
            }

            // منع حذف مدير الشركة الأخير
            if ($user->role === 'admin') {
                $adminCount = User::where('company_id', $companyId)->where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return $this->fail('لا يمكن حذف آخر مدير في الشركة', null, 409);
                }
            }

            // منع الحذف إن كان له حركات
            $tables = ['journal_entries','sales_invoices','purchase_invoices','receipt_vouchers','payment_vouchers'];
            foreach ($tables as $t) {
                if (DB::table($t)->where('company_id', $companyId)->where('user_id', $userId)->exists()) {
                    return $this->fail("لا يمكن حذف مستخدم له حركات في {$t}", null, 409);
                }
            }

            return DB::transaction(function () use ($user, $companyId) {
                DB::table('user_branches')->where('user_id', $user->id)->delete();
                DB::table('user_roles')->where('user_id', $user->id)->delete();
                DB::table('personal_access_tokens')
                    ->where('tokenable_id', $user->id)
                    ->where('tokenable_type', User::class)
                    ->delete();
                $user->delete();

                $count = User::where('company_id', $companyId)->count();
                Company::where('id', $companyId)->update(['current_users_count' => $count]);

                return $this->ok('تم حذف المستخدم بنجاح');
            });
        } catch (Throwable $e) {
            Log::error('UserService@delete: ' . $e->getMessage());
            return $this->fail('فشل حذف المستخدم: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        جلب
    // ============================================================

    public function getById(int $userId, int $companyId): array
    {
        try {
            $user = User::with(['company','branch'])
                ->where('company_id', $companyId)
                ->find($userId);
            if (!$user) return $this->fail('المستخدم غير موجود', null, 404);

            $user->branch_ids = DB::table('user_branches')
                ->where('user_id', $user->id)
                ->pluck('branch_id')->all();

            return $this->ok('تم الجلب', $user);
        } catch (Throwable $e) {
            Log::error('UserService@getById: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    public function list(int $companyId, array $filters = []): array
    {
        try {
            $q = User::with(['branch'])
                ->where('company_id', $companyId);

            if (!empty($filters['branch_id'])) {
                $q->where(function ($w) use ($filters) {
                    $w->where('branch_id', $filters['branch_id'])
                      ->orWhereHas('userBranches', fn($q2) => $q2->where('branch_id', $filters['branch_id']));
                });
            }
            if (!empty($filters['role'])) {
                $q->where('role', $filters['role']);
            }
            if (isset($filters['is_active']) && $filters['is_active'] !== '') {
                $q->where('is_active', $filters['is_active']);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $q->where(function ($w) use ($s) {
                    $w->where('name', 'LIKE', "%{$s}%")
                      ->orWhere('mobile', 'LIKE', "%{$s}%")
                      ->orWhere('email', 'LIKE', "%{$s}%");
                });
            }

            return $this->ok('تم الجلب', $q->orderBy('name')->get());
        } catch (Throwable $e) {
            Log::error('UserService@list: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }
}
