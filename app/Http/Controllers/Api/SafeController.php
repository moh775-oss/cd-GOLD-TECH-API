<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SafeService;
use Illuminate\Http\Request;

class SafeController extends Controller
{
    public function __construct(private SafeService $service) {}

    public function index(Request $request)
    {
        $result = $this->service->list($request->user()->company_id, $request->only(['branch_id','is_active','search']));
        return response()->json($result, $result['code']);
    }

    public function show(Request $request, int $id)
    {
        $result = $this->service->getById($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function create() { return response()->json(['success'=>true,'message'=>'نموذج الإنشاء','data'=>null,'code'=>200],200); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id'    => 'required|integer',
            'name'         => 'required|string|max:150',
            'code'         => 'nullable|string|max:30',
            'opening_cash' => 'nullable|numeric',
            'opening_gold' => 'nullable|numeric',
            'currency'     => 'nullable|string|max:10',
            'is_active'    => 'nullable|integer|in:0,1',
            'notes'        => 'nullable|string|max:500',
        ]);
        $data['company_id'] = $request->user()->company_id;
        $data['user_id']    = $request->user()->id;
        $result = $this->service->create($data);
        return response()->json($result, $result['code']);
    }

    public function edit(Request $request, int $id) { return $this->show($request, $id); }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name'      => 'sometimes|string|max:150',
            'code'      => 'nullable|string|max:30',
            'currency'  => 'nullable|string|max:10',
            'is_active' => 'nullable|integer|in:0,1',
            'notes'     => 'nullable|string|max:500',
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
