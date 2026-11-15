<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
                'data'    => null,
                'code'    => 401,
            ], 401);
        }

        $has = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $user->id)
            ->where('user_roles.company_id', $user->company_id)
            ->where('permissions.code', $permission)
            ->exists();

        if (!$has) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية: ' . $permission,
                'data'    => null,
                'code'    => 403,
            ], 403);
        }

        return $next($request);
    }
}
