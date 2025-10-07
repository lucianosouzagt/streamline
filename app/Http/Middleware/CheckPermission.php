<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado',
            ], 401);
        }

        $user = Auth::user();

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado. Permissão necessária: ' . $permission,
            ], 403);
        }

        return $next($request);
    }
}