<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Respuesta paginada con la forma del contrato: { data: [...], meta: {current_page, per_page, total, last_page} }. */
final class Paginated
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @param  class-string<JsonResource>  $resource
     */
    public static function response(LengthAwarePaginator $page, string $resource, Request $request): JsonResponse
    {
        return response()->json([
            'data' => $resource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
