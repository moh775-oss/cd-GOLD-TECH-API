<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    public function stats(Request $request)
    {
        $result = $this->service->getStats(
            $request->user()->company_id,
            $request->filled('branch_id') ? (int)$request->branch_id : null
        );
        return response()->json($result, $result['code']);
    }
}