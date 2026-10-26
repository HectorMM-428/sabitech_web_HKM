import { useCallback } from 'react';
import { useSearchParams } from 'react-router';

/**
 * Filtros y página de un listado guardados en la URL (?status=&q=&page=), como en
 * el programa público: se pueden compartir y el botón «atrás» funciona.
 */
export function useListParams<K extends string>(keys: readonly K[]) {
  const [searchParams, setSearchParams] = useSearchParams();
  const values = Object.fromEntries(keys.map((k) => [k, searchParams.get(k) ?? ''])) as Record<K, string>;
  const page = Math.max(1, Number(searchParams.get('page')) || 1);

  const setFilters = useCallback(
    (next: Partial<Record<K, string>>) => {
      setSearchParams((prev) => {
        const params = new URLSearchParams(prev);
        for (const [k, v] of Object.entries(next) as [K, string | undefined][]) {
          if (v) params.set(k, v);
          else params.delete(k);
        }
        params.delete('page'); // Un filtro nuevo vuelve a la primera página.
        return params;
      });
    },
    [setSearchParams],
  );

  const setPage = useCallback(
    (target: number) => {
      setSearchParams((prev) => {
        const params = new URLSearchParams(prev);
        if (target > 1) params.set('page', String(target));
        else params.delete('page');
        return params;
      });
    },
    [setSearchParams],
  );

  return { values, page, setFilters, setPage, key: searchParams.toString() };
}
