<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BranchService;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(private BranchService $service) {}

    public function index(Request $request)
    {
        $result = $this->service->list($request->user()->company_id, $request->only(['is_active','search']));
        return response()->json($result, $result['code']);
    }

    public function create()
    {
        return response()->json([
            'success' => true,
            'message' => 'نموذج إنشاء فرع',
            'data'    => null,
            'code'    => 200,
        ], 200);
    }

    public function show(Request $request, int $id)
    {
        $result = $this->service->getById($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function edit(Request $request, int $id)
    {
        $result = $this->service->getById($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'code'        => 'nullable|string|max:30',
            'phone'       => 'nullable|string|max:30',
            'mobile'      => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:150',
            'address'     => 'nullable|string|max:400',
            'city'        => 'nullable|string|max:100',
            'district'    => 'nullable|string|max:100',
            'street'      => 'nullable|string|max:200',
            'building_no' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:20',
            'logo_path'   => 'nullable|string|max:300',
            'cover_path'  => 'nullable|string|max:300',
            'is_main'     => 'nullable|integer|in:0,1',
            'is_active'   => 'nullable|integer|in:0,1',
            'notes'       => 'nullable|string|max:1000',
        ]);
        $data['company_id'] = $request->user()->company_id;
        $data['user_id']    = $request->user()->id;
        $result = $this->service->create($data);
        return response()->json($result, $result['code']);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:150',
            'code'        => 'nullable|string|max:30',
            'phone'       => 'nullable|string|max:30',
            'mobile'      => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:150',
            'address'     => 'nullable|string|max:400',
            'city'        => 'nullable|string|max:100',
            'district'    => 'nullable|string|max:100',
            'street'      => 'nullable|string|max:200',
            'building_no' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:20',
            'logo_path'   => 'nullable|string|max:300',
            'cover_path'  => 'nullable|string|max:300',
            'is_main'     => 'nullable|integer|in:0,1',
            'is_active'   => 'nullable|integer|in:0,1',
            'notes'       => 'nullable|string|max:1000',
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
}
