<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Autenticación de la SPA con sesión (Sanctum en modo SPA, AD-02).
 *
 * El navegador guarda una cookie de sesión HttpOnly que JavaScript no puede leer.
 * Al entrar o salir se regenera el identificador de sesión (evita la fijación de sesión).
 */
class AuthController extends Controller
{
    /** A05 · POST /api/v1/auth/register */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Solo nombre, email y contraseña: el rol queda en `attendee` (valor por defecto).
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /** A04 · POST /api/v1/auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::guard('web')->attempt($credentials)) {
            // Mismo mensaje si falla el email o la contraseña: no revela qué cuentas existen.
            throw ValidationException::withMessages([
                'email' => 'El email o la contraseña no son correctos.',
            ]);
        }

        $request->session()->regenerate();

        return (new UserResource($request->user()))->response();
    }

    /** A06 · POST /api/v1/auth/logout */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /** A07 · GET /api/v1/auth/me */
    public function me(Request $request): JsonResponse
    {
        return (new UserResource($request->user()))->response();
    }
}
