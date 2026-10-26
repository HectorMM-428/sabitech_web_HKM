import { useState, type FormEvent } from 'react';
import { listAdminReservations, type ReservationFilters } from '../../api/admin';
import { SelectField, TextField } from '../../components/ui/Form';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { EmailBadge, ReservationBadge } from '../../components/ui/StatusBadge';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { formatDay, formatTime } from '../../lib/datetime';
import { Pager, ResultCount } from './Pager';
import { useListParams } from './useListParams';

/** A23 · Reservas con filtros por estado, charla y texto (nombre, correo o referencia). */
export function ReservationsAdminPage() {
  useDocumentTitle('Reservas · Panel');
  const { values, page, setFilters, setPage, key } = useListParams(['status', 'q', 'talk_id'] as const);
  const [draftQ, setDraftQ] = useState(values.q);
  const filters: ReservationFilters = {
    status: values.status as ReservationFilters['status'],
    q: values.q,
    talk_id: values.talk_id ? Number(values.talk_id) : '',
    page,
  };
  const { state, reload } = useApi((signal) => listAdminReservations(filters, signal), `admin-res-${key}`);

  function onSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setFilters({ q: draftQ.trim() });
  }

  return (
    <>
      <h1>Reservas</h1>
      <form className="toolbar" role="search" aria-label="Filtrar reservas" onSubmit={onSearch}>
        <SelectField label="Estado" value={values.status} onChange={(e) => setFilters({ status: e.target.value })}>
          <option value="">Todos</option>
          <option value="confirmed">Confirmada</option>
          <option value="attended">Asistida</option>
          <option value="cancelled">Cancelada</option>
        </SelectField>
        <TextField label="Buscar" type="search" maxLength={100} placeholder="Nombre, correo o referencia" value={draftQ} onChange={(e) => setDraftQ(e.target.value)} />
        <button type="submit" className="button button--primary">
          Buscar
        </button>
        {values.talk_id ? (
          <button type="button" className="button button--secondary" onClick={() => setFilters({ talk_id: '' })}>
            Ver todas las charlas
          </button>
        ) : null}
      </form>

      {state.status === 'loading' && <LoadingBlocks label="Cargando reservas…" count={4} height={48} />}
      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}
      {state.status === 'success' && (
        <>
          <ResultCount total={state.data.meta.total} noun={['reserva', 'reservas']} />
          {state.data.data.length === 0 ? (
            <EmptyState title="No hay reservas con estos filtros" />
          ) : (
            <div className="table-wrap">
              <table className="data-table data-table--cards">
                <caption className="visually-hidden">Reservas</caption>
                <thead>
                  <tr>
                    <th scope="col">Referencia</th>
                    <th scope="col">Asistente</th>
                    <th scope="col">Charla</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Correo</th>
                    <th scope="col">Reservada</th>
                  </tr>
                </thead>
                <tbody>
                  {state.data.data.map((r) => (
                    <tr key={r.id}>
                      <td data-label="Referencia" className="mono">
                        {r.reference}
                      </td>
                      <td data-label="Asistente">
                        {r.attendee.name}
                        <br />
                        <span className="muted">{r.attendee.email}</span>
                      </td>
                      <td data-label="Charla">
                        <button type="button" className="link-button" onClick={() => setFilters({ talk_id: String(r.talk_id) })}>
                          {r.talk_title}
                        </button>
                      </td>
                      <td data-label="Estado">
                        <ReservationBadge status={r.display_status} />
                      </td>
                      <td data-label="Correo">
                        <EmailBadge status={r.email_status} />
                      </td>
                      <td data-label="Reservada">
                        {formatDay(r.confirmed_at)}, {formatTime(r.confirmed_at)}
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
