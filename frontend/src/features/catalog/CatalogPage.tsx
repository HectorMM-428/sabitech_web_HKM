import { useState, type FormEvent } from 'react';
import { useSearchParams } from 'react-router';
import { listTalks } from '../../api/talks';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { TalkCard } from './TalkCard';

/**
 * Programa público (A01). Los filtros viven en la URL (?q=&date=&page=), así una
 * búsqueda se puede compartir y el botón «atrás» del navegador funciona.
 */
export function CatalogPage() {
  useDocumentTitle('Programa');
  const [searchParams, setSearchParams] = useSearchParams();
  const q = searchParams.get('q') ?? '';
  const date = searchParams.get('date') ?? '';
  const page = Math.max(1, Number(searchParams.get('page')) || 1);

  const [draftQ, setDraftQ] = useState(q);
  const [draftDate, setDraftDate] = useState(date);

  const { state, reload } = useApi((signal) => listTalks({ q, date, page }, signal), `${q}|${date}|${page}`);

  function applyFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const next = new URLSearchParams();
    if (draftQ.trim()) next.set('q', draftQ.trim());
    if (draftDate) next.set('date', draftDate);
    setSearchParams(next);
  }

  function clearFilters() {
    setDraftQ('');
    setDraftDate('');
    setSearchParams(new URLSearchParams());
  }

  function goToPage(target: number) {
    const next = new URLSearchParams(searchParams);
    if (target > 1) next.set('page', String(target));
    else next.delete('page');
    setSearchParams(next);
  }

  const hasFilters = Boolean(q || date);

  return (
    <div className="page">
      <header className="page-header">
        <h1>Programa de charlas</h1>
        <p className="page-header__lead">Consulta las charlas de las jornadas SABITECH y sus plazas disponibles.</p>
      </header>

      <form className="filters" role="search" aria-label="Filtrar charlas" onSubmit={applyFilters}>
        <div className="field">
          <label className="field__label" htmlFor="filter-q">
            Buscar
          </label>
          <input
            id="filter-q"
            className="field__input"
            type="search"
            value={draftQ}
            maxLength={100}
            placeholder="Título, tema o ponente"
            onChange={(e) => setDraftQ(e.target.value)}
          />
        </div>
        <div className="field">
          <label className="field__label" htmlFor="filter-date">
            Día
          </label>
          <input
            id="filter-date"
            className="field__input"
            type="date"
            value={draftDate}
            onChange={(e) => setDraftDate(e.target.value)}
          />
        </div>
        <div className="filters__actions">
          <button type="submit" className="button button--primary">
            Aplicar
          </button>
          {hasFilters ? (
            <button type="button" className="button button--secondary" onClick={clearFilters}>
              Quitar filtros
            </button>
          ) : null}
        </div>
      </form>

      {state.status === 'loading' && <LoadingBlocks label="Cargando charlas…" count={6} />}

      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}

      {state.status === 'success' && (
        <>
          <p className="results-count" role="status" aria-live="polite">
            {state.data.meta.total === 1 ? '1 charla' : `${state.data.meta.total} charlas`}
            {hasFilters ? ' con estos filtros' : ''}
          </p>

          {state.data.data.length === 0 ? (
            <EmptyState title={hasFilters ? 'No hay charlas con estos filtros' : 'Todavía no hay charlas publicadas'}>
              {hasFilters ? 'Prueba con otro día o con otras palabras.' : 'Vuelve más adelante para consultar el programa.'}
            </EmptyState>
          ) : (
            <ul className="talk-grid">
              {state.data.data.map((talk) => (
                <li key={talk.id}>
                  <TalkCard talk={talk} />
                </li>
              ))}
            </ul>
          )}

          {state.data.meta.last_page > 1 ? (
            <nav className="pagination" aria-label="Paginación">
              <button
                type="button"
                className="button button--secondary"
                disabled={page <= 1}
                onClick={() => goToPage(page - 1)}
              >
                Anterior
              </button>
              <span>
                Página {state.data.meta.current_page} de {state.data.meta.last_page}
              </span>
              <button
                type="button"
                className="button button--secondary"
                disabled={page >= state.data.meta.last_page}
                onClick={() => goToPage(page + 1)}
              >
                Siguiente
              </button>
            </nav>
          ) : null}
        </>
      )}
    </div>
  );
}
