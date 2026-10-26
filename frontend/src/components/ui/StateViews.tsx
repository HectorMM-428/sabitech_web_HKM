import type { ReactNode } from 'react';
import { ApiError, NETWORK_ERROR } from '../../api/client';

interface EmptyStateProps {
  title: string;
  children?: ReactNode;
}

export function EmptyState({ title, children }: EmptyStateProps) {
  return (
    <div className="state">
      <p className="state__title">{title}</p>
      {children ? <div className="state__text">{children}</div> : null}
    </div>
  );
}

interface ErrorStateProps {
  error: ApiError;
  onRetry?: () => void;
}

/** Error de carga con su causa real y, si tiene sentido, un botón para reintentar. */
export function ErrorState({ error, onRetry }: ErrorStateProps) {
  const title = error.code === NETWORK_ERROR ? 'Sin conexión con el servidor' : 'No se han podido cargar los datos';
  return (
    <div className="state state--error" role="alert">
      <p className="state__title">
        <span aria-hidden="true">✕ </span>
        {title}
      </p>
      <p className="state__text">{error.message}</p>
      {onRetry ? (
        <button type="button" className="button button--secondary" onClick={onRetry}>
          Reintentar
        </button>
      ) : null}
    </div>
  );
}

interface LoadingBlocksProps {
  count?: number;
  height?: number;
  label: string;
}

/** Esqueletos de carga. Visualmente ocupan el sitio del contenido; al lector de pantalla le basta la etiqueta. */
export function LoadingBlocks({ count = 3, height = 160, label }: LoadingBlocksProps) {
  return (
    <div role="status" aria-live="polite">
      <span className="visually-hidden">{label}</span>
      <div className="loading-grid" aria-hidden="true">
        {Array.from({ length: count }, (_, i) => (
          <div key={i} className="skeleton" style={{ height }} />
        ))}
      </div>
    </div>
  );
}
