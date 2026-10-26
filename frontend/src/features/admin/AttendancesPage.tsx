import { listAttendances } from '../../api/admin';
import { SelectField } from '../../components/ui/Form';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { formatDay, formatTime } from '../../lib/datetime';
import { METHOD_LABEL } from './labels';
import { Pager, ResultCount } from './Pager';
import { useListParams } from './useListParams';

/** A24 · Asistencias: quién validó, cuándo y, si fue manual, por qué. */
export function AttendancesPage() {
  useDocumentTitle('Asistencias · Panel');
  const { values, page, setFilters, setPage, key } = useListParams(['method'] as const);
  const method = values.method as 'qr' | 'manual' | '';
  const { state, reload } = useApi((signal) => listAttendances({ method, page }, signal), `admin-att-${key}`);

  return (
    <>
      <h1>Asistencias</h1>
      <div className="toolbar">
        <SelectField label="Método" value={method} onChange={(e) => setFilters({ method: e.target.value })}>
          <option value="">Todos</option>
          <option value="qr">QR</option>
          <option value="manual">Manual</option>
        </SelectField>
      </div>

      {state.status === 'loading' && <LoadingBlocks label="Cargando asistencias…" count={4} height={48} />}
      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}
      {state.status === 'success' && (
        <>
          <ResultCount total={state.data.meta.total} noun={['asistencia', 'asistencias']} />
          {state.data.data.length === 0 ? (
            <EmptyState title="Todavía no hay asistencias registradas" />
          ) : (
            <div className="table-wrap">
              <table className="data-table data-table--cards">
                <caption className="visually-hidden">Asistencias registradas</caption>
                <thead>
                  <tr>
                    <th scope="col">Asistente</th>
                    <th scope="col">Charla</th>
                    <th scope="col">Método</th>
                    <th scope="col">Validó</th>
                    <th scope="col">Hora</th>
                  </tr>
                </thead>
                <tbody>
                  {state.data.data.map((a) => (
                    <tr key={a.id}>
                      <td data-label="Asistente">
                        {a.attendee_name}
                        <br />
                        <span className="mono muted">{a.reservation_reference}</span>
                      </td>
                      <td data-label="Charla">{a.talk_title}</td>
                      <td data-label="Método">
                        {METHOD_LABEL[a.method]}
                        {a.manual_reason ? (
                          <>
                            <br />
                            <span className="muted">Motivo: {a.manual_reason}</span>
                          </>
                        ) : null}
                      </td>
                      <td data-label="Validó">{a.validated_by}</td>
                      <td data-label="Hora">
                        {formatDay(a.validated_at)}, {formatTime(a.validated_at)}
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
