<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** A30 y A27 · Usuarios y roles. Es la única forma de dar el rol de staff o admin. */
class UserController extends Controller
{
    /** A30 · Buscar usuarios por nombre o correo, y filtrar por rol. */
    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $page = User::query()
            ->when($v['role'] ?? null, fn (Builder $q, string $role) => $q->where('role', $role))
            ->when(filled($v['q'] ?? null), function (Builder $q) use ($v) {
                $term = '%'.addcslashes(trim($v['q']), '\\%_').'%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(config('sabitech.per_page'));

        return Paginated::response($page, UserResource::class, $request);
    }

    /**
     * A27 · Cambiar el rol. Nadie puede cambiar el suyo: así un admin no se quita
     * el acceso por error y siempre queda al menos uno.
     */
    public function role(Request $request, int $user): JsonResponse
    {
        $v = $request->validate(
            ['role' => ['required', Rule::enum(UserRole::class)]],
            ['role.required' => 'Elige un rol.', 'role.enum' => 'El rol no es válido.'],
        );

        $model = User::query()->findOrFail($user);

        if ($model->is($request->user())) {
            throw ValidationException::withMessages(['role' => 'No puedes cambiar tu propio rol.']);
        }

        // `role` no es asignable en masa (RT-04): se cambia de forma explícita.
        $model->role = UserRole::from($v['role']);
        $model->save();

        return response()->json(['data' => (new UserResource($model))->resolve($request)]);
    }
}
