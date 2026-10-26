<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\EmailNotification;
use App\Services\NotificationService;
use App\Support\Paginated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A25 y A26 · Correos de confirmación en el panel de organización. */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** A25 · GET /api/v1/admin/notifications?status= */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'sending', 'sent', 'failed'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $page = EmailNotification::query()
            ->with('reservation')
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(config('sabitech.per_page'));

        return Paginated::response($page, NotificationResource::class, $request);
    }

    /** A26 · POST /api/v1/admin/notifications/{id}/retry */
    public function retry(Request $request, int $notification): JsonResponse
    {
        $model = EmailNotification::query()->findOrFail($notification);
        $retried = $this->notifications->retry($model)->load('reservation');

        return response()->json(['data' => (new NotificationResource($retried))->resolve($request)], 202);
    }
}
