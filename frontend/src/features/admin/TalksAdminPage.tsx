import { useState } from 'react';
import { Link, useLocation } from 'react-router';
import { changeTalkStatus, listAdminTalks } from '../../api/admin';
import { ApiError } from '../../api/client';
import type { TalkDetail, TalkStatus } from '../../api/types';
import { Button, FormAlert, SelectField } from '../../components/ui/Form';
import { Modal } from '../../components/ui/Modal';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { formatDay, formatTimeRange } from '../../lib/datetime';
import { TALK_STATUS } from './labels';
import { Pager, ResultCount } from './Pager';
import { useListParams } from './useListParams';

type Action = { talk: TalkDetail; to: TalkStatus };

const ACTION_TEXT: Record<TalkStatus, string> = { published: 'Publicar', draft: 'Despublicar', cancelled: 'Cancelar charla' };

/** A17 y A20 · Charlas en cualquier estado, con sus acciones. */
export function TalksAdminPage() {
  useDocumentTitle('Charlas · Panel');
  const { values, page, setFilters, setPage, key } = useListParams(['status'] as const);
  const status = values.status as TalkStatus | '';
  const { state, reload } = useApi((signal) => listAdminTalks({ status, page }, signal), `admin-talks-${key}`);
  const [action, setAction] = useState<Action | null>(null);
  const saved = (useLocation().state as { saved?: string } | null)?.saved;
  const [notice, setNotice] = useState<string | null>(saved ? `Se ha guardado «${saved}».` : null);

  return (
    <>
      <div className="admin-header">
        <h1>Charlas</h1>
        <Link className="button button--primary" to="/admin/charlas/nueva">
          Nueva charla
        </Link>
      </div>

      <div className="toolbar">
        <SelectField label="Estado" value={status} onChange={(e) => setFilters({ status: e.target.value })}>
          <option value="">Todos</option>
          <option value="draft">Borrador</option>
          <option value="published">Publicada</option>
          <option value="cancelled">Cancelada</option>
        </SelectField>
      </div>

      {notice ? <FormAlert tone="success">{notice}</FormAlert> : null}

      {state.status === 'loading' && <LoadingBlocks label="Cargando charlas…" count={4} height={56} />}
      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}
      {state.status === 'success' && (
        <>
          <ResultCount total={state.data.meta.total} noun={['charla', 'charlas']} />
          {state.data.data.length === 0 ? (
            <EmptyState title="No hay charlas con este filtro" />
          ) : (
            <div className="table-wrap">
              <table className="data-table data-table--cards">
                <caption className="visually-hidden">Charlas</caption>
                <thead>
                  <tr>
                    <th scope="col">Charla</th>
                    <th scope="col">Cuándo</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Ocupación</th>
                    <th scope="col">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {state.data.data.map((talk) => (
                    <tr key={talk.id}>
                      <td data-label="Charla">
                        <strong>{talk.title}</strong>
                        <br />
                        <span className="muted">
                          {talk.speaker.name} · {talk.room.name}
                        </span>
                      </td>
                      <td data-label="Cuándo">
                        {formatDay(talk.starts_at)}
                        <br />
                        <span className="mono">{formatTimeRange(talk.starts_at, talk.ends_at)}</span>
                      </td>
                      <td data-label="Estado">
                        <span className={`badge badge--${TALK_STATUS[talk.status].tone}`}>
                          <span aria-hidden="true">{TALK_STATUS[talk.status].icon}</span> {TALK_STATUS[talk.status].label}
                        </span>
                      </td>
                      <td data-label="Ocupación">
                        {talk.reservations_confirmed ?? 0}/{talk.capacity} reservas
                        <br />
                        <span className="muted">{talk.attendances ?? 0} asistencias</span>
                      </td>
                      <td data-label="Acciones">
                        <div className="row-actions">
                          {talk.status !== 'cancelled' ? (
                            <Link className="button button--secondary button--small" to={`/admin/charlas/${talk.id}`} aria-label={`Editar ${talk.title}`}>
                              Editar
                            </Link>
                          ) : null}
                          {nextStatuses(talk).map((to) => (
                            <Button
                              key={to}
                              variant={to === 'cancelled' ? 'danger' : 'secondary'}
                              className="button--small"
                              aria-label={`${ACTION_TEXT[to]}: ${talk.title}`}
                              onClick={() => {
                                setNotice(null);
                                setAction({ talk, to });
                              }}
                            >
                              {ACTION_TEXT[to]}
                            </Button>
                          ))}
                        </div>
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

      {action ? (
        <StatusDialog
          action={action}
          onClose={() => setAction(null)}
          onDone={(talk) => {
            setAction(null);
            setNotice(`«${talk.title}» ahora está ${TALK_STATUS[talk.status].label.toLowerCase()}.`);
            reload();
          }}
        />
      ) : null}
    </>
  );
}

/** Acciones posibles según el estado (las mismas reglas que valida el servidor). */
function nextStatuses(talk: TalkDetail): TalkStatus[] {
  if (talk.status === 'cancelled' || Date.parse(talk.starts_at) <= Date.now()) return [];
  if (talk.status === 'draft') return ['published', 'cancelled'];
  return (talk.reservations_confirmed ?? 0) > 0 ? ['cancelled'] : ['draft', 'cancelled'];
}

function StatusDialog({ action, onClose, onDone }: { action: Action; onClose: () => void; onDone: (t: TalkDetail) => void }) {
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { talk, to } = action;
  const reservations = talk.reservations_confirmed ?? 0;

  async function confirm() {
    setSubmitting(true);
    setError(null);
    try {
      const res = await changeTalkStatus(talk.id, to);
      onDone(res.data);
    } catch (e) {
      const err = e instanceof ApiError ? e : null;
      setError(err?.fields?.status?.[0] ?? err?.message ?? 'Ha ocurrido un error inesperado.');
      setSubmitting(false);
    }
  }

  return (
    <Modal
      title={`${ACTION_TEXT[to]}: ${talk.title}`}
      role={to === 'cancelled' ? 'alertdialog' : 'dialog'}
      onClose={onClose}
      actions={
        <>
          <Button variant={to === 'cancelled' ? 'danger' : 'primary'} onClick={confirm} loading={submitting} loadingText="Guardando…">
            {ACTION_TEXT[to]}
          </Button>
          <Button variant="secondary" onClick={onClose} disabled={submitting}>
            Volver
          </Button>
        </>
      }
    >
      {to === 'published' && <p>La charla aparecerá en el programa y se podrán reservar plazas.</p>}
      {to === 'draft' && <p>La charla dejará de verse en el programa.</p>}
      {to === 'cancelled' && (
        <p>
          La charla dejará de verse en el programa
          {reservations > 0 ? ` y se cancelarán sus ${reservations} reservas confirmadas` : ''}. Esta acción no se puede deshacer.
        </p>
      )}
      {error ? <FormAlert>{error}</FormAlert> : null}
    </Modal>
  );
}
