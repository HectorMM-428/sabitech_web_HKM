import { useState } from 'react';
import { listNotifications, retryNotification } from '../../api/admin';
import { ApiError } from '../../api/client';
import type { Notification, NotificationStatus } from '../../api/types';
import { Button, FormAlert, SelectField } from '../../components/ui/Form';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { EmailBadge } from '../../components/ui/StatusBadge';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { formatDay, formatTime } from '../../lib/datetime';
import { Pager, ResultCount } from './Pager';
import { useListParams } from './useListParams';

/** A25 y A26 · Correos de confirmación con su estado real y reintento manual. */
export function NotificationsPage() {
  useDocumentTitle('Correos · Panel');
  const { values, page, setFilters, setPage, key } = useListParams(['status'] as const);
  const status = values.status as NotificationStatus | '';
  const { state, reload } = useApi((signal) => listNotifications({ status, page }, signal), `admin-mail-${key}`);
  const [retrying, setRetrying] = useState<number | null>(null);
  const [message, setMessage] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);

  async function retry(n: Notification) {
    setRetrying(n.id);
    setMessage(null);
    try {
      await retryNotification(n.id);
      setMessage({ tone: 'success', text: `Reintento encolado para ${n.recipient}. La reserva ${n.reservation_reference} no cambia.` });
      reload();
    } catch (e) {
      setMessage({ tone: 'error', text: e instanceof ApiError ? e.message : 'Ha ocurrido un error inesperado.' });
    } finally {
      setRetrying(null);
    }
  }

  return (
    <>
      <h1>Correos de confirmación</h1>
      <p className="muted page-intro">
        Un correo fallido no anula la reserva. Se reintenta solo hasta 5 veces; aquí puedes forzar un nuevo intento.
      </p>
      <div className="toolbar">
        <SelectField label="Estado" value={status} onChange={(e) => setFilters({ status: e.target.value })}>
          <option value="">Todos</option>
          <option value="failed">No enviado</option>
          <option value="pending">Pendiente</option>
          <option value="sending">Enviando</option>
          <option value="sent">Enviado</option>
        </SelectField>
      </div>

      {message ? <FormAlert tone={message.tone}>{message.text}</FormAlert> : null}

      {state.status === 'loading' && <LoadingBlocks label="Cargando correos…" count={4} height={48} />}
      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}
      {state.status === 'success' && (
        <>
          <ResultCount total={state.data.meta.total} noun={['correo', 'correos']} />
          {state.data.data.length === 0 ? (
            <EmptyState title="No hay correos con este estado" />
          ) : (
            <div className="table-wrap">
              <table className="data-table data-table--cards">
                <caption className="visually-hidden">Correos de confirmación</caption>
                <thead>
                  <tr>
                    <th scope="col">Reserva</th>
                    <th scope="col">Destinatario</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Intentos</th>
                    <th scope="col">Último error</th>
                    <th scope="col">Acción</th>
                  </tr>
                </thead>
                <tbody>
                  {state.data.data.map((n) => (
                    <tr key={n.id}>
                      <td data-label="Reserva" className="mono">
                        {n.reservation_reference}
                      </td>
                      <td data-label="Destinatario">{n.recipient}</td>
                      <td data-label="Estado">
                        <EmailBadge status={n.status} />
                      </td>
                      <td data-label="Intentos">
                        {n.attempts}
                        {n.last_attempt_at ? (
                          <>
                            <br />
                            <span className="muted">
                              {formatDay(n.last_attempt_at)}, {formatTime(n.last_attempt_at)}
                            </span>
                          </>
                        ) : null}
                      </td>
                      <td data-label="Último error">{n.last_error ?? '—'}</td>
                      <td data-label="Acción">
                        {n.status === 'failed' || n.status === 'pending' ? (
                          <Button
                            variant="secondary"
                            className="button--small"
                            onClick={() => retry(n)}
                            loading={retrying === n.id}
                            loadingText="Encolando…"
                            disabled={retrying !== null}
                            aria-label={`Reintentar el correo de ${n.reservation_reference}`}
                          >
                            Reintentar
                          </Button>
                        ) : (
                          '—'
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          <Pager meta={state.data.meta} onPage={setPage} />
        </>
      )}
    </>
  );
}
