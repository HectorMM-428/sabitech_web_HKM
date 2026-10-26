import { useState } from 'react';
import { Link } from 'react-router';
import { listStaffTalks } from '../../api/access';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { useNow } from '../../hooks/useNow';
import { eventDateKey, formatDay, formatTimeRange } from '../../lib/datetime';

/** A13 · El personal elige la charla que va a controlar. */
export function AccessHomePage() {
  useDocumentTitle('Control de acceso');
  const now = useNow();
  const [date, setDate] = useState(() => eventDateKey(new Date(now).toISOString()));
  const { state, reload } = useApi((signal) => listStaffTalks(date, signal), `staff-${date}`);

  return (
    <div className="page">
      <h1>Control de acceso</h1>
      <p className="muted page-intro">Elige la charla y valida las acreditaciones QR en la entrada.</p>

      <div className="toolbar">
        <div className="field">
          <label className="field__label" htmlFor="access-date">
            Día
          </label>
          <input id="access-date" className="field__input" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
        </div>
      </div>

      {state.status === 'loading' && <LoadingBlocks label="Cargando las charlas del día…" count={3} height={96} />}
      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}
      {state.status === 'success' &&
        (state.data.data.length === 0 ? (
          <EmptyState title={`No hay charlas el ${formatDay(`${date}T12:00:00+01:00`)}`}>Elige otro día.</EmptyState>
        ) : (
          <ul className="reservation-list">
            {state.data.data.map((talk) => (
              <li key={talk.id}>
                <article className="reservation-card">
                  <div className="reservation-card__main">
                    <h2 className="reservation-card__title">{talk.title}</h2>
                    <p className="muted">
                      <span className="mono">{formatTimeRange(talk.starts_at, talk.ends_at)}</span> · {talk.room.name}
                    </p>
                    <p>
                      {talk.reservations_confirmed} reservas · {talk.attendances} asistencias registradas
                    </p>
                  </div>
                  <div className="reservation-card__actions">
                    <Link className="button button--primary" to={`/acceso/${talk.id}`} aria-label={`Abrir control de ${talk.title}`}>
                      Abrir control
                    </Link>
                  </div>
                </article>
              </li>
            ))}
          </ul>
        ))}
    </div>
  );
}
