<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTalksRequest;
use App\Http\Resources\TalkResource;
use App\Queries\PublishedTalksQuery;
use Illuminate\Http\JsonResponse;

/**
 * Programa público. El controlador solo valida, delega en la consulta y da forma
 * a la respuesta; la regla de qué charlas se muestran está en PublishedTalksQuery.
 */
class TalkController extends Controller
{
    public function __construct(private readonly PublishedTalksQuery $talks) {}

    /** A01 · GET /api/v1/talks */
    public function index(ListTalksRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $page = $this->talks->paginate(
            date: $filters['date'] ?? null,
            search: $filters['q'] ?? null,
            perPage: config('sabitech.per_page'),
        );

        return response()->json([
            'data' => TalkResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /** A02 · GET /api/v1/talks/{id}. Un borrador o una charla cancelada responden 404. */
    public function show(int $talk): JsonResponse
    {
        $model = $this->talks->findOrFail($talk);

        return response()->json([
            'data' => (new TalkResource($model))->detailed()->resolve(request()),
        ]);
    }
}
