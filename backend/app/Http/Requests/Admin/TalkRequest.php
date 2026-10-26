<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Esquema TalkInput del contrato (A18 y A19). El rol ya lo comprueba el middleware. */
class TalkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'speaker_id' => ['required', 'integer', 'exists:speakers,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Escribe el título.',
            'title.min' => 'El título debe tener al menos 3 caracteres.',
            'title.max' => 'El título admite como máximo 160 caracteres.',
            'description.required' => 'Escribe la descripción.',
            'description.min' => 'La descripción debe tener al menos 10 caracteres.',
            'description.max' => 'La descripción admite como máximo 5000 caracteres.',
            'speaker_id.required' => 'Elige un ponente.',
            'speaker_id.exists' => 'El ponente no existe.',
            'room_id.required' => 'Elige una sala.',
            'room_id.exists' => 'La sala no existe.',
            'starts_at.required' => 'Indica la hora de inicio.',
            'starts_at.date' => 'La hora de inicio no es válida.',
            'ends_at.required' => 'Indica la hora de fin.',
            'ends_at.date' => 'La hora de fin no es válida.',
            'ends_at.after' => 'La hora de fin debe ser posterior a la de inicio.',
            'capacity.required' => 'Indica el aforo.',
            'capacity.integer' => 'El aforo debe ser un número entero.',
            'capacity.min' => 'El aforo debe ser al menos 1.',
            'capacity.max' => 'El aforo máximo es 1000.',
        ];
    }
}
