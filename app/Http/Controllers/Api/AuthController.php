<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    /**
     * الخطوة 1: التحقق من الجوال وكلمة المرور
     * ترجع قائمة الفروع والسنوات المالية المتاحة
     */
    public function checkCredentials(Request $request)
    {
        try {
            $data = $request->validate([
                'mobile'   => 'required|string',
                'password' => 'required|string',
            ]);

            $user = User::where('mobile', $data['mobile'])->where('is_active', 1)->first();
            if (!$user || !Hash::check($data['password'], $user->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الهاتف أو كلمة المرور غير صحيحة',
                    'data'    => null,
                    'code'    => 401,
                ], 401);
            }

            $company = Company::where('id', $user->company_id)->where('is_active', 1)->first();
            if (!$company) {
                return response()->json(['success' => false, 'message' => 'الشركة غير مفعلة', 'data' => null, 'code' => 403], 403);
            }
            if ($company->subscription_end && $company->subscription_end < now()->toDateString()) {
                return response()->json(['success' => false, 'message' => 'انتهى اشتراك الشركة', 'data' => null, 'code' => 403], 403);
            }

            $branches = Branch::where('company_id', $user->company_id)
                ->where('is_active', 1)
                ->orderBy('is_main', 'desc')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_main']);

            $fiscalYears = FiscalYear::where('company_id', $user->company_id)
                ->where('is_active', 1)
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'start_date', 'end_date', 'is_closed']);

            return response()->json([
                'success' => true,
                'message' => 'بيانات صحيحة، اختر الفرع والسنة المالية',
                'data'    => [
                    'user' => [
                        'id'         => $user->id,
                        'name'       => $user->name,
                        'mobile'     => $user->mobile,
                        'email'      => $user->email,
                        'role'       => $user->role,
                        'company_id' => $user->company_id,
                        'company_name' => $company->name,
                        'branch_id'  => $user->branch_id,
                    ],
                    'branches'     => $branches,
                    'fiscal_years' => $fiscalYears,
                ],
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            Log::error('checkCredentials: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'خطأ: ' . $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    /**
     * الخطوة 2: تسجيل الدخول النهائي بعد اختيار الفرع والسنة المالية
     */
    public function login(Request $request)
    {
        try {
            $data = $request->validate([
                'mobile'         => 'required|string',
                'password'       => 'required|string',
                'branch_id'      => 'required|integer',
                'fiscal_year_id' => 'required|integer',
            ]);

            $user = User::where('mobile', $data['mobile'])->where('is_active', 1)->first();
            if (!$user || !Hash::check($data['password'], $user->password_hash)) {
                return response()->json(['success' => false, 'message' => 'بيانات الدخول غير صحيحة', 'data' => null, 'code' => 401], 401);
            }

            // ← أضف هذا
            $company = Company::find($user->company_id);
            if (!$company) {
                return response()->json(['success' => false, 'message' => 'الشركة غير موجودة', 'data' => null, 'code' => 403], 403);
            }

            $branch = Branch::where('id', $data['branch_id'])
                ->where('company_id', $user->company_id)
                ->where('is_active', 1)
                ->first();
            if (!$branch) {
                return response()->json(['success' => false, 'message' => 'الفرع غير صالح', 'data' => null, 'code' => 403], 403);
            }

            $fiscalYear = FiscalYear::where('id', $data['fiscal_year_id'])
                ->where('company_id', $user->company_id)
                ->where('is_active', 1)
                ->first();
            if (!$fiscalYear) {
                return response()->json(['success' => false, 'message' => 'السنة المالية غير صالحة', 'data' => null, 'code' => 403], 403);
            }
            if ($fiscalYear->is_closed) {
                return response()->json(['success' => false, 'message' => 'السنة المالية مغلقة', 'data' => null, 'code' => 403], 403);
            }

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
                'branch_id'     => $branch->id,
            ]);

            $token = $user->createToken('auth', [
                'company_id'     => $user->company_id,
                'branch_id'      => $branch->id,
                'fiscal_year_id' => $fiscalYear->id,
            ])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح',
                'data'    => [
                    'token' => $token,
                    'user'  => [
                        'id'             => $user->id,
                        'name'           => $user->name,
                        'mobile'         => $user->mobile,
                        'email'          => $user->email,
                        'role'           => $user->role,
                        'company_id'     => $user->company_id,
                        'company_name'   => $company->name,   // ← أضف هذا
                        'branch_id'      => $branch->id,
                        'branch_name'    => $branch->name,
                        'fiscal_year_id' => $fiscalYear->id,
                        'fiscal_year'    => $fiscalYear->name,
                    ],
                ],
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            Log::error('login: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'خطأ: ' . $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    /**
     * تسجيل الخروج
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'تم الخروج', 'data' => null, 'code' => 200], 200);
    }
}
