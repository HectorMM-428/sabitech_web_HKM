<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validación de los filtros del programa (A01). */
class ListTalksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Programa público.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'q.max' => 'La búsqueda admite como máximo 100 caracteres.',
            'page.integer' => 'La página debe ser un número.',
            'page.min' => 'La página debe ser 1 o mayor.',
        ];
    }
}
