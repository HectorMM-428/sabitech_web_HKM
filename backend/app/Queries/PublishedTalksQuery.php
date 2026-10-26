<?php

namespace App\Queries;

use App\Models\Talk;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consulta del programa público. Separada del controlador para que la regla
 * «qué se muestra» esté en un solo sitio y se pueda probar.
 */
final class PublishedTalksQuery
{
    /**
     * @param  string|null  $date  Día en la zona de las jornadas (YYYY-MM-DD).
     * @return LengthAwarePaginator<int, Talk>
     */
    public function paginate(?string $date, ?string $search, int $perPage): LengthAwarePaginator
    {
        $query = $this->base();

        if ($date) {
            // El día se interpreta en Europe/Madrid y se convierte a un rango UTC,
            // porque en la base de datos las horas están en UTC (RNF-04).
            $zone = config('app.display_timezone');
            $from = CarbonImmutable::createFromFormat('Y-m-d', $date, $zone)->startOfDay();
            $query->where('starts_at', '>=', $from->utc())
                ->where('starts_at', '<', $from->addDay()->utc());
        }

        if ($search !== null && trim($search) !== '') {
            // Escapar comodines: buscar «100%» no debe devolver todo el programa.
            $term = '%'.addcslashes(trim($search), '\\%_').'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('speaker', fn (Builder $s) => $s->where('name', 'like', $term));
            });
        }

        return $query->orderBy('starts_at')->orderBy('id')->paginate($perPage);
    }

    public function findOrFail(int $id): Talk
    {
        return $this->base()->findOrFail($id);
    }

    /** @return Builder<Talk> */
    private function base(): Builder
    {
        return Talk::query()
            ->published()
            ->withAvailableSeats()
            ->with(['speaker', 'room']);
    }
}
