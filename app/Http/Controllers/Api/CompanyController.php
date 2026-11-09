<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CompanyService;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $service) {}

    public function index(Request $request)
    {
        $result = $this->service->list(
            $request->only(['is_active','subscription_status','search'])
        );
        return response()->json($result, $result['code']);
    }

    public function create()
    {
        return response()->json([
            'success' => true,
            'message' => 'نموذج إنشاء شركة',
            'data'    => [
                'plans' => [
                    ['value' => 'trial',      'label' => 'تجريبي'],
                    ['value' => 'basic',      'label' => 'أساسي'],
                    ['value' => 'pro',        'label' => 'احترافي'],
                    ['value' => 'enterprise', 'label' => 'مؤسسي'],
                ],
                'cycles' => [
                    ['value' => 'monthly', 'label' => 'شهري'],
                    ['value' => 'yearly',  'label' => 'سنوي'],
                ],
            ],
            'code' => 200,
        ], 200);
    }

    public function show(int $id)
    {
        $result = $this->service->getById($id);
        return response()->json($result, $result['code']);
    }

    public function edit(int $id)
    {
        $result = $this->service->getById($id);
        return response()->json($result, $result['code']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:200',
            'name_en'             => 'nullable|string|max:200',
            'mobile'              => 'required|string|max:30',
            'password'            => 'required|string|min:6',
            'admin_name'          => 'required|string|max:150',
            'commercial_register' => 'nullable|string|max:50',
            'tax_number'          => 'nullable|string|max:50',
            'phone'               => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:150',
            'website'             => 'nullable|string|max:150',
            'country'             => 'nullable|string|max:100',
            'city'                => 'nullable|string|max:100',
            'district'            => 'nullable|string|max:100',
            'street'              => 'nullable|string|max:200',
            'building_no'         => 'nullable|string|max:20',
            'postal_code'         => 'nullable|string|max:20',
            'extra_no'            => 'nullable|string|max:20',
            'address'             => 'nullable|string|max:400',
            'location_url'        => 'nullable|string|max:400',
            'logo_path'           => 'nullable|string|max:300',
            'cover_path'          => 'nullable|string|max:300',
            'subscription_plan'   => 'nullable|string|max:50',
            'subscription_start'  => 'nullable|date',
            'subscription_end'    => 'nullable|date',
            'subscription_amount' => 'nullable|numeric|min:0',
            'subscription_cycle'  => 'nullable|in:monthly,yearly',
            'max_users'           => 'nullable|integer|min:1',
            'max_branches'        => 'nullable|integer|min:1',
            'max_contacts'        => 'nullable|integer|min:0',
            'branch_name'         => 'nullable|string|max:150',
            'store_name'          => 'nullable|string|max:150',
            'safe_name'           => 'nullable|string|max:150',
            'fiscal_year_name'    => 'nullable|string|max:100',
            'fiscal_year_start'   => 'nullable|date',
            'fiscal_year_end'     => 'nullable|date',
        ]);

        $data['user_id'] = $request->user()->id;
        $result = $this->service->create($data);
        return response()->json($result, $result['code']);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name'                => 'sometimes|string|max:200',
            'name_en'             => 'nullable|string|max:200',
            'commercial_register' => 'nullable|string|max:50',
            'tax_number'          => 'nullable|string|max:50',
            'phone'               => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:150',
            'website'             => 'nullable|string|max:150',
            'city'                => 'nullable|string|max:100',
            'district'            => 'nullable|string|max:100',
            'street'              => 'nullable|string|max:200',
            'building_no'         => 'nullable|string|max:20',
            'postal_code'         => 'nullable|string|max:20',
            'extra_no'            => 'nullable|string|max:20',
            'address'             => 'nullable|string|max:400',
            'location_url'        => 'nullable|string|max:400',
            'logo_path'           => 'nullable|string|max:300',
            'cover_path'          => 'nullable|string|max:300',
            'subscription_plan'   => 'nullable|string|max:50',
            'subscription_end'    => 'nullable|date',
            'subscription_amount' => 'nullable|numeric|min:0',
            'subscription_status' => 'nullable|in:active,expired,suspended,trial',
            'max_users'           => 'nullable|integer|min:1',
            'max_branches'        => 'nullable|integer|min:1',
            'is_active'           => 'nullable|integer|in:0,1',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $result = $this->service->update($id, $data);
        return response()->json($result, $result['code']);
    }

    public function destroy(int $id)
    {
        $result = $this->service->delete($id);
        return response()->json($result, $result['code']);
    }
}
