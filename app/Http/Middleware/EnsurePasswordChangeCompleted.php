<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChangeCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->force_password_change) {
            return response()->json([
                'message' => 'Troca de senha obrigatória pendente.',
                'force_password_change' => true,
            ], 423);
        }

        return $next($request);
    }
}
