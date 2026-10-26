<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Http\Resources\SpeakerResource;
use App\Models\Room;
use App\Models\Speaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * A21 y A22 · Ponentes y salas. Son listas cortas (decenas), por eso no se paginan.
 * No se borra nada que tenga charlas: la clave foránea lo impide igualmente
 * (restrictOnDelete), pero aquí se responde con un mensaje claro (HAS_TALKS).
 */
class CatalogDataController extends Controller
{
    // --- Ponentes -----------------------------------------------------------

    public function speakers(Request $request): JsonResponse
    {
        return response()->json([
            'data' => SpeakerResource::collection(Speaker::query()->orderBy('name')->get())->resolve($request),
        ]);
    }

    public function storeSpeaker(Request $request): JsonResponse
    {
        $speaker = Speaker::create($this->speakerData($request));

        return response()->json(['data' => (new SpeakerResource($speaker))->resolve($request)], 201);
    }

    public function updateSpeaker(Request $request, int $speaker): JsonResponse
    {
        $model = Speaker::query()->findOrFail($speaker);
        $model->update($this->speakerData($request));

        return response()->json(['data' => (new SpeakerResource($model))->resolve($request)]);
    }

    public function destroySpeaker(int $speaker): Response
    {
        $model = Speaker::query()->findOrFail($speaker);
        if ($model->talks()->exists()) {
            throw new BusinessRuleException('HAS_TALKS', 'Este ponente tiene charlas asociadas: no se puede borrar.');
        }
        $model->delete();

        return response()->noContent();
    }

    // --- Salas --------------------------------------------------------------

    public function rooms(Request $request): JsonResponse
    {
        return response()->json([
            'data' => RoomResource::collection(Room::query()->orderBy('name')->get())->resolve($request),
        ]);
    }

    public function storeRoom(Request $request): JsonResponse
    {
        $room = Room::create($this->roomData($request));

        return response()->json(['data' => (new RoomResource($room))->resolve($request)], 201);
    }

    public function updateRoom(Request $request, int $room): JsonResponse
    {
        $model = Room::query()->findOrFail($room);
        $model->update($this->roomData($request, $model->id));

        return response()->json(['data' => (new RoomResource($model))->resolve($request)]);
    }

    public function destroyRoom(int $room): Response
    {
        $model = Room::query()->findOrFail($room);
        if ($model->talks()->exists()) {
            throw new BusinessRuleException('HAS_TALKS', 'Esta sala tiene charlas asociadas: no se puede borrar.');
        }
        $model->delete();

        return response()->noContent();
    }

    // --- Validación -----------------------------------------------------------

    /** @return array{name: string, affiliation: ?string, bio: ?string, photo_alt: ?string} */
    private function speakerData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'affiliation' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'photo_alt' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Escribe el nombre.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre admite como máximo 120 caracteres.',
            'affiliation.max' => 'La organización admite como máximo 160 caracteres.',
            'bio.max' => 'La biografía admite como máximo 2000 caracteres.',
            'photo_alt.max' => 'El texto alternativo admite como máximo 255 caracteres.',
        ]);

        return [
            'name' => trim($data['name']),
            'affiliation' => $this->blankToNull($data['affiliation'] ?? null),
            'bio' => $this->blankToNull($data['bio'] ?? null),
            'photo_alt' => $this->blankToNull($data['photo_alt'] ?? null),
        ];
    }

    /** @return array{name: string, location_details: ?string} */
    private function roomData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('rooms', 'name')->ignore($ignoreId)],
            'location_details' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Escribe el nombre de la sala.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre admite como máximo 80 caracteres.',
            'name.unique' => 'Ya existe una sala con ese nombre.',
            'location_details.max' => 'La ubicación admite como máximo 255 caracteres.',
        ]);

        return [
            'name' => trim($data['name']),
            'location_details' => $this->blankToNull($data['location_details'] ?? null),
        ];
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
