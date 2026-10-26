import type { PageMeta } from '../../api/types';

/** Paginación de los listados del panel (20 filas por página, decidido en el contrato). */
export function Pager({ meta, onPage }: { meta: PageMeta; onPage: (page: number) => void }) {
  if (meta.last_page <= 1) return null;
  return (
    <nav className="pagination" aria-label="Paginación">
      <button type="button" className="button button--secondary" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>
        Anterior
      </button>
      <span>
        Página {meta.current_page} de {meta.last_page}
      </span>
      <button
        type="button"
        className="button button--secondary"
        disabled={meta.current_page >= meta.last_page}
        onClick={() => onPage(meta.current_page + 1)}
      >
        Siguiente
      </button>
    </nav>
  );
}

/** «38 resultados», anunciado al cambiar los filtros. */
export function ResultCount({ total, noun }: { total: number; noun: [string, string] }) {
  return (
    <p className="results-count" role="status" aria-live="polite">
      {total} {total === 1 ? noun[0] : noun[1]}
    </p>
  );
}
