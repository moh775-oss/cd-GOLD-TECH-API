<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Concerns\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BranchService
{
    use ApiResponse;

    /**
     * إنشاء فرع مع التحقق من حد الشركة (max_branches)
     */
    public function create(array $data): array
    {
        try {
            // 1. الشركة موجودة
            $company = Company::where('id', $data['company_id'])->where('is_active', 1)->first();
            if (!$company) return $this->fail('الشركة غير موجودة أو غير مفعلة', null, 403);

            // 2. لم ينتهِ الاشتراك
            if ($company->subscription_end && $company->subscription_end < now()->toDateString()) {
                return $this->fail('انتهى اشتراك الشركة', null, 403);
            }

            // 3. حد الفروع
            $currentCount = Branch::where('company_id', $company->id)->count();
            if ($company->max_branches > 0 && $currentCount >= $company->max_branches) {
                return $this->fail('وصلت الشركة للحد الأقصى من الفروع (' . $company->max_branches . ')', null, 403);
            }

            // 4. الحقول الإلزامية
            if (empty($data['name'])) return $this->fail('اسم الفرع مطلوب', null, 422);

            // 5. منع تكرار code
            if (!empty($data['code'])) {
                $dup = Branch::where('company_id', $company->id)->where('code', $data['code'])->exists();
                if ($dup) return $this->fail('رمز الفرع مستخدم مسبقاً', null, 409);
            }

            // 6. الإنشاء
            return DB::transaction(function () use ($data, $company) {
                $branch = Branch::create([
                    'company_id'  => $company->id,
                    'name'        => $data['name'],
                    'code'        => $data['code']        ?? null,
                    'phone'       => $data['phone']       ?? null,
                    'mobile'      => $data['mobile']      ?? null,
                    'email'       => $data['email']       ?? null,
                    'address'     => $data['address']     ?? null,
                    'city'        => $data['city']        ?? null,
                    'district'    => $data['district']    ?? null,
                    'street'      => $data['street']      ?? null,
                    'building_no' => $data['building_no'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'is_main'     => $data['is_main']     ?? 0,
                    'is_active'   => $data['is_active']   ?? 1,
                    'notes'       => $data['notes']       ?? null,
                    'user_id'     => $data['user_id']     ?? Auth::id(),
                ]);

                // تحديث عداد الفروع في الشركة
                Company::where('id', $company->id)->update([
                    'current_branches_count' => $currentCount + 1,
                ]);

                return $this->ok('تم إنشاء الفرع بنجاح', $branch, 201);
            });
        } catch (Throwable $e) {
            Log::error('BranchService@create: ' . $e->getMessage());
            return $this->fail('فشل إنشاء الفرع: ' . $e->getMessage(), null, 500);
        }
    }

    public function update(int $id, int $companyId, array $data): array
    {
        try {
            $branch = Branch::where('id', $id)->where('company_id', $companyId)->first();
            if (!$branch) return $this->fail('الفرع غير موجود', null, 404);

            if (!empty($data['code']) && $data['code'] !== $branch->code) {
                $dup = Branch::where('company_id', $companyId)->where('code', $data['code'])->where('id', '!=', $id)->exists();
                if ($dup) return $this->fail('رمز الفرع مستخدم مسبقاً', null, 409);
            }

            $branch->update([
                'name'        => $data['name']        ?? $branch->name,
                'code'        => $data['code']        ?? $branch->code,
                'phone'       => $data['phone']       ?? $branch->phone,
                'mobile'      => $data['mobile']      ?? $branch->mobile,
                'email'       => $data['email']       ?? $branch->email,
                'address'     => $data['address']     ?? $branch->address,
                'city'        => $data['city']        ?? $branch->city,
                'district'    => $data['district']    ?? $branch->district,
                'street'      => $data['street']      ?? $branch->street,
                'building_no' => $data['building_no'] ?? $branch->building_no,
                'postal_code' => $data['postal_code'] ?? $branch->postal_code,
                'is_main'     => $data['is_main']     ?? $branch->is_main,
                'is_active'   => $data['is_active']   ?? $branch->is_active,
                'notes'       => $data['notes']       ?? $branch->notes,
                'user_id'     => $data['user_id']     ?? Auth::id(),
            ]);

            return $this->ok('تم تعديل الفرع بنجاح', $branch);
        } catch (Throwable $e) {
            Log::error('BranchService@update: ' . $e->getMessage());
            return $this->fail('فشل تعديل الفرع: ' . $e->getMessage(), null, 500);
        }
    }

    public function delete(int $id, int $companyId): array
    {
        try {
            $branch = Branch::where('id', $id)->where('company_id', $companyId)->first();
            if (!$branch) return $this->fail('الفرع غير موجود', null, 404);

            // منع حذف الفرع الرئيسي
            if ($branch->is_main) return $this->fail('لا يمكن حذف الفرع الرئيسي', null, 409);

            // منع الحذف إن كان له مستخدمون أو حركات
            if (User::where('company_id', $companyId)->where('branch_id', $id)->exists()) {
                return $this->fail('لا يمكن حذف فرع له مستخدمون', null, 409);
            }

            $tables = ['sales_invoices','purchase_invoices','sales_returns','purchase_returns','sales_wages','purchase_wages','receipt_vouchers','payment_vouchers','journal_entries','expenses','safes','banks','contacts','items'];
            foreach ($tables as $tbl) {
                if (DB::table($tbl)->where('company_id', $companyId)->where('branch_id', $id)->exists()) {
                    return $this->fail("لا يمكن حذف فرع له بيانات في {$tbl}", null, 409);
                }
            }

            return DB::transaction(function () use ($branch, $companyId) {
                $branch->delete();
                $count = Branch::where('company_id', $companyId)->count();
                Company::where('id', $companyId)->update(['current_branches_count' => $count]);
                return $this->ok('تم حذف الفرع بنجاح');
            });
        } catch (Throwable $e) {
            Log::error('BranchService@delete: ' . $e->getMessage());
            return $this->fail('فشل حذف الفرع: ' . $e->getMessage(), null, 500);
        }
    }

    public function getById(int $id, int $companyId): array
    {
        try {
            $branch = Branch::where('company_id', $companyId)->find($id);
            if (!$branch) return $this->fail('الفرع غير موجود', null, 404);
            return $this->ok('تم الجلب', $branch);
        } catch (Throwable $e) {
            Log::error('BranchService@getById: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    public function list(int $companyId, array $filters = []): array
    {
        try {
            $q = Branch::where('company_id', $companyId);
            if (isset($filters['is_active']) && $filters['is_active'] !== '') $q->where('is_active', $filters['is_active']);
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $q->where(function ($w) use ($s) {
                    $w->where('name', 'LIKE', "%{$s}%")->orWhere('code', 'LIKE', "%{$s}%")->orWhere('city', 'LIKE', "%{$s}%");
                });
            }
            return $this->ok('تم الجلب', $q->orderBy('is_main', 'desc')->orderBy('name')->get());
        } catch (Throwable $e) {
            Log::error('BranchService@list: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }
}
