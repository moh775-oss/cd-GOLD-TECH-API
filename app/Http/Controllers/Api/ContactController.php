<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContactService;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(private ContactService $contactService) {}

    public function index(Request $request)
    {
        $result = $this->contactService->list(
            $request->user()->company_id,
            $request->only(['branch_id','contact_type','is_active','search'])
        );
        return response()->json($result, $result['code']);
    }

    public function show(Request $request, int $id)
    {
        $result = $this->contactService->getById($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function create()
    {
        return response()->json(['success' => true, 'message' => 'نموذج الإنشاء', 'data' => null, 'code' => 200], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id'           => 'required|integer',
            'name'                => 'required|string|max:200',
            'name_en'             => 'nullable|string|max:200',
            'contact_type'        => 'required|in:customer,supplier,both',
            'phone'               => 'nullable|string|max:30',
            'mobile'              => 'nullable|string|max:30',
            'whatsapp'            => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:150',
            'website'             => 'nullable|string|max:150',
            'tax_number'          => 'nullable|string|max:50',
            'commercial_register' => 'nullable|string|max:50',
            'national_id'         => 'nullable|string|max:30',
            'address'             => 'nullable|string|max:400',
            'city'                => 'nullable|string|max:100',
            'district'            => 'nullable|string|max:100',
            'street'              => 'nullable|string|max:200',
            'building_no'         => 'nullable|string|max:20',
            'postal_code'         => 'nullable|string|max:20',
            'extra_no'            => 'nullable|string|max:20',
            'country'             => 'nullable|string|max:100',
            'credit_limit'        => 'nullable|numeric|min:0',
            'opening_balance'     => 'nullable|numeric',
            'notes'               => 'nullable|string|max:1000',
            'is_active'           => 'nullable|integer|in:0,1',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $data['user_id']    = $request->user()->id;

        $result = $this->contactService->create($data);
        return response()->json($result, $result['code']);
    }

    public function edit(Request $request, int $id)
    {
        return $this->show($request, $id);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name'                => 'sometimes|string|max:200',
            'name_en'             => 'nullable|string|max:200',
            'contact_type'        => 'sometimes|in:customer,supplier,both',
            'phone'               => 'nullable|string|max:30',
            'mobile'              => 'nullable|string|max:30',
            'whatsapp'            => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:150',
            'website'             => 'nullable|string|max:150',
            'tax_number'          => 'nullable|string|max:50',
            'commercial_register' => 'nullable|string|max:50',
            'national_id'         => 'nullable|string|max:30',
            'address'             => 'nullable|string|max:400',
            'city'                => 'nullable|string|max:100',
            'district'            => 'nullable|string|max:100',
            'street'              => 'nullable|string|max:200',
            'building_no'         => 'nullable|string|max:20',
            'postal_code'         => 'nullable|string|max:20',
            'extra_no'            => 'nullable|string|max:20',
            'country'             => 'nullable|string|max:100',
            'credit_limit'        => 'nullable|numeric|min:0',
            'opening_balance'     => 'nullable|numeric',
            'notes'               => 'nullable|string|max:1000',
            'is_active'           => 'nullable|integer|in:0,1',
        ]);
        $data['user_id'] = $request->user()->id;

        $result = $this->contactService->update($id, $request->user()->company_id, $data);
        return response()->json($result, $result['code']);
    }

    public function destroy(Request $request, int $id)
    {
        $result = $this->contactService->delete($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }
}
