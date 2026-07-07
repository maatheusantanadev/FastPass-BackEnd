<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe o acesso a uma rota a usuários com um determinado `role`.
 *
 * Uso: Route::middleware('role:motorista') ou 'role:motorista,administrador'.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! in_array($usuario->role, $perfis, true)) {
            return response()->json([
                'mensagem' => 'Acesso não autorizado para este perfil.',
            ], 403);
        }

        return $next($request);
    }
}
