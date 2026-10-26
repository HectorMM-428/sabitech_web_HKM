<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TalkStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TalkRequest;
use App\Http\Resources\TalkResource;
use App\Models\Talk;
use App\Services\TalkAdminService;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A17–A20 · Gestión de charlas. Ve todas, también borradores y canceladas. */
class TalkController extends Controller
{
    public function __construct(private readonly TalkAdminService $talks) {}

    /** A17 */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(TalkStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $page = $this->withAdminData(Talk::query())
            ->when($validated['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate(config('sabitech.per_page'));

        return response()->json([
            'data' => collect($page->items())->map(fn (Talk $t) => $this->present($t, $request))->all(),
            'meta' => Paginated::meta($page),
        ]);
    }

    /** A17 · Una charla en cualquier estado, para el formulario de edición. */
    public function show(Request $request, int $talk): JsonResponse
    {
        return response()->json(['data' => $this->present($this->withAdminData(Talk::query())->findOrFail($talk), $request)]);
    }

    /** A18 · Se crea como borrador. */
    public function store(TalkRequest $request): JsonResponse
    {
        $talk = $this->talks->create($request->validated());

        return response()->json(['data' => $this->fresh($talk, $request)], 201);
    }

    /** A19 */
    public function update(TalkRequest $request, int $talk): JsonResponse
    {
        $updated = $this->talks->update(Talk::query()->findOrFail($talk), $request->validated());

        return response()->json(['data' => $this->fresh($updated, $request)]);
    }

    /** A20 */
    public function status(Request $request, int $talk): JsonResponse
    {
        $validated = $request->validate(
            ['status' => ['required', Rule::enum(TalkStatus::class)]],
            ['status.required' => 'Indica el estado.', 'status.enum' => 'El estado no es válido.'],
        );

        $updated = $this->talks->changeStatus(Talk::query()->findOrFail($talk), TalkStatus::from($validated['status']));

        return response()->json(['data' => $this->fresh($updated, $request)]);
    }

    /**
     * @param  Builder<Talk>  $query
     * @return Builder<Talk>
     */
    private function withAdminData(Builder $query): Builder
    {
        return $query->with(['speaker', 'room'])->withAvailableSeats()->withCount('attendances');
    }

    /** @return array<string, mixed> */
    private function fresh(Talk $talk, Request $request): array
    {
        return $this->present($this->withAdminData(Talk::query())->findOrFail($talk->id), $request);
    }

    /** @return array<string, mixed> */
    private function present(Talk $talk, Request $request): array
    {
        return (new TalkResource($talk))->detailed()->resolve($request) + [
            'reservations_confirmed' => (int) $talk->getAttribute('confirmed_reservations_count'),
            'attendances' => (int) $talk->getAttribute('attendances_count'),
        ];
    }
}
