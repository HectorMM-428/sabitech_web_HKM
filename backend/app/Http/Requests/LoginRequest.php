<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A04 · Inicio de sesión. */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Escribe tu email.',
            'email.email' => 'El email no tiene un formato válido.',
            'password.required' => 'Escribe tu contraseña.',
        ];
    }
}
