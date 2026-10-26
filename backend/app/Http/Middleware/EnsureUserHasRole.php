<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Autorización por rol aplicada en el servidor (R07). Ocultar un botón en el
 * frontend no protege nada: esta comprobación sí.
 *
 * Uso en rutas:  ->middleware('role:staff,admin')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        if (! $user || ! $user->hasRole(...$allowed)) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
