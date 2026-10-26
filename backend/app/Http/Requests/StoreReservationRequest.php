<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A10 · Validación de la entrada. Si la charla existe y tiene plaza lo decide el servicio. */
class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El rol ya lo comprueba el middleware de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'talk_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'talk_id.required' => 'Indica la charla que quieres reservar.',
            'talk_id.integer' => 'La charla indicada no es válida.',
            'talk_id.min' => 'La charla indicada no es válida.',
        ];
    }
}
