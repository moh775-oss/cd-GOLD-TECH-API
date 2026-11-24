<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyInfoController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $branchId = (int)$request->input('branch_id', $user->branch_id);

        $info = CompanyInfo::where('company_id', $user->company_id)
            ->where('branch_id', $branchId)
            ->first();

        if (!$info) {
            return response()->json(['success' => false, 'message' => 'غير موجود', 'data' => null, 'code' => 404], 404);
        }

        return response()->json([
            'success' => true, 'message' => 'تم الجلب',
            'data' => $this->present($info), 'code' => 200,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $branchId = (int)$request->input('branch_id', $user->branch_id);

        $data = $request->validate([
            'name'                => 'sometimes|string|max:200',
            'name_en'             => 'nullable|string|max:200',
            'phone'               => 'nullable|string|max:30',
            'mobile'              => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:150',
            'website'             => 'nullable|string|max:200',
            'address'             => 'nullable|string|max:500',
            'city'                => 'nullable|string|max:100',
            'country'             => 'nullable|string|max:100',
            'district'            => 'nullable|string|max:100',
            'street'              => 'nullable|string|max:200',
            'building_no'         => 'nullable|string|max:20',
            'postal_code'         => 'nullable|string|max:20',
            'tax_number'          => 'nullable|string|max:50',
            'commercial_register' => 'nullable|string|max:50',
        ]);

        $info = CompanyInfo::where('company_id', $user->company_id)
            ->where('branch_id', $branchId)
            ->first();

        if (!$info) {
            return response()->json(['success' => false, 'message' => 'غير موجود', 'data' => null, 'code' => 404], 404);
        }

        $info->update(array_merge($data, ['user_id' => $user->id]));

        return response()->json([
            'success' => true, 'message' => 'تم التعديل',
            'data' => $this->present($info->fresh()), 'code' => 200,
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $user = $request->user();
        $branchId = (int)$request->input('branch_id', $user->branch_id);

        $info = CompanyInfo::where('company_id', $user->company_id)
            ->where('branch_id', $branchId)
            ->first();
        if (!$info) {
            return response()->json(['success' => false, 'message' => 'غير موجود', 'data' => null, 'code' => 404], 404);
        }

        if ($info->logo_path && Storage::disk('public')->exists($info->logo_path)) {
            Storage::disk('public')->delete($info->logo_path);
        }

        $path = $request->file('logo')->store("companies/{$user->company_id}/logos", 'public');
        $info->update(['logo_path' => $path, 'user_id' => $user->id]);

        return response()->json([
            'success' => true, 'message' => 'تم رفع الشعار',
            'data' => $this->present($info->fresh()), 'code' => 200,
        ]);
    }

    private function present(CompanyInfo $info): array
    {
        return array_merge($info->toArray(), [
            'logo_url' => $info->logo_path ? asset('storage/'.$info->logo_path) : null,
        ]);
    }
}
