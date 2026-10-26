<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** A05 · Registro. No hay campo `role`: cualquier valor enviado se ignora. */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(10)->letters()->numbers()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Escribe tu nombre.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre admite como máximo 120 caracteres.',
            'email.required' => 'Escribe tu email.',
            'email.email' => 'El email no tiene un formato válido.',
            'email.max' => 'El email admite como máximo 190 caracteres.',
            // K-12: en un evento pequeño se prioriza un mensaje claro.
            'email.unique' => 'Ya existe una cuenta con este email. Inicia sesión.',
            'password.required' => 'Escribe una contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 10 caracteres.',
            'password.letters' => 'La contraseña debe incluir al menos una letra.',
            'password.numbers' => 'La contraseña debe incluir al menos un número.',
        ];
    }
}
