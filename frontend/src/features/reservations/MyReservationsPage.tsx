import { useState } from 'react';
import { Link } from 'react-router';
import { ApiError } from '../../api/client';
import { cancelReservation, listMyReservations } from '../../api/reservations';
import type { Reservation } from '../../api/types';
import { Button, FormAlert } from '../../components/ui/Form';
import { Modal } from '../../components/ui/Modal';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { EmailBadge, ReservationBadge } from '../../components/ui/StatusBadge';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { useNow } from '../../hooks/useNow';
import { formatDay, formatTimeRange } from '../../lib/datetime';

/** A08 + A11 · Mis reservas, con su estado, el estado del correo y la cancelación. */
export function MyReservationsPage() {
  useDocumentTitle('Mis reservas');
  const { state, reload } = useApi((signal) => listMyReservations(undefined, signal), 'mine-all');
  const [toCancel, setToCancel] = useState<Reservation | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const now = useNow();
  const isUpcoming = (r: Reservation) => r.display_status === 'confirmed' && Date.parse(r.talk.ends_at) > now;

  return (
    <div className="page">
      <h1>Mis reservas</h1>
      <p className="muted page-intro">Tus plazas en las charlas de SABITECH. Muestra tu acreditación QR en la entrada de cada charla.</p>

      <div aria-live="polite" role="status">
        {notice ? <FormAlert tone="success">{notice}</FormAlert> : null}
      </div>

      {state.status === 'loading' && <LoadingBlocks label="Cargando tus reservas…" count={3} height={140} />}
      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}

      {state.status === 'success' &&
        (state.data.data.length === 0 ? (
          <EmptyState title="Todavía no tienes reservas">
            <Link to="/">Consulta el programa</Link> y reserva tu plaza.
          </EmptyState>
        ) : (
          <>
            <ReservationList
              title="Próximas"
              items={state.data.data.filter(isUpcoming)}
              empty="No tienes charlas próximas."
              onCancel={setToCancel}
              now={now}
            />
            <ReservationList
              title="Historial"
              items={state.data.data.filter((r) => !isUpcoming(r))}
              empty="Aún no hay reservas pasadas, canceladas o asistidas."
              onCancel={setToCancel}
              now={now}
            />
          </>
        ))}

      {toCancel ? (
        <CancelDialog
          reservation={toCancel}
          onClose={() => setToCancel(null)}
          onCancelled={(r) => {
            setToCancel(null);
            setNotice(`Reserva ${r.reference} cancelada. La plaza ha quedado libre.`);
            reload();
          }}
        />
      ) : null}
    </div>
  );
}

function ReservationList({
  title,
  items,
  empty,
  onCancel,
  now,
}: {
  title: string;
  items: Reservation[];
  empty: string;
  onCancel: (r: Reservation) => void;
  now: number;
}) {
  return (
    <section aria-labelledby={`list-${title}`}>
      <h2 id={`list-${title}`} className="section-title">
        {title}
      </h2>
      {items.length === 0 ? (
        <p className="muted">{empty}</p>
      ) : (
        <ul className="reservation-list">
          {items.map((r) => (
            <li key={r.id}>
              <ReservationCard reservation={r} onCancel={onCancel} now={now} />
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function ReservationCard({ reservation: r, onCancel, now }: { reservation: Reservation; onCancel: (r: Reservation) => void; now: number }) {
  const notStarted = Date.parse(r.talk.starts_at) > now;
  const canCancel = r.display_status === 'confirmed' && notStarted;
  const canShowCredential = r.display_status === 'confirmed' && Date.parse(r.talk.ends_at) > now;

  return (
    <article className="reservation-card">
      <div className="reservation-card__main">
        <h3 className="reservation-card__title">
          <Link to={`/charlas/${r.talk.id}`}>{r.talk.title}</Link>
        </h3>
        <p className="muted">
          {formatDay(r.talk.starts_at)} · <span className="mono">{formatTimeRange(r.talk.starts_at, r.talk.ends_at)}</span> · {r.talk.room.name}
        </p>
        <p className="reservation-card__ref">
          Referencia <span className="mono">{r.reference}</span>
        </p>
        <div className="reservation-card__badges">
          <ReservationBadge status={r.display_status} />
          {r.status === 'confirmed' ? <EmailBadge status={r.email_status} /> : null}
        </div>
        {r.email_status === 'failed' && r.status === 'confirmed' ? (
          <p className="field__hint">Tu reserva es válida. La organización reenviará el correo de confirmación.</p>
        ) : null}
      </div>
      <div className="reservation-card__actions">
        {canShowCredential ? (
          <Link className="button button--primary" to={`/mis-reservas/${r.id}/acreditacion`}>
            Mostrar acreditación
          </Link>
        ) : null}
        {canCancel ? (
          <Button variant="danger" onClick={() => onCancel(r)}>
            Cancelar reserva
          </Button>
        ) : null}
      </div>
    </article>
  );
}

function CancelDialog({
  reservation,
  onClose,
  onCancelled,
}: {
  reservation: Reservation;
  onClose: () => void;
  onCancelled: (r: Reservation) => void;
}) {
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  async function confirm() {
    setSubmitting(true);
    setError(null);
    try {
      const res = await cancelReservation(reservation.id);
      onCancelled(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.'));
      setSubmitting(false);
    }
  }

  return (
    <Modal
      title="¿Cancelar la reserva?"
      onClose={onClose}
      actions={
        <>
          <Button variant="danger" onClick={confirm} loading={submitting} loadingText="Cancelando…">
            Sí, cancelar
          </Button>
          <Button variant="secondary" onClick={onClose} disabled={submitting}>
            Mantener la reserva
          </Button>
        </>
      }
    >
      <p>
        Vas a cancelar tu plaza en <strong>{reservation.talk.title}</strong>. La plaza quedará libre para otra persona.
      </p>
      {error ? (
        <div style={{ marginTop: 'var(--space-3)' }}>
          <FormAlert>{error.message}</FormAlert>
        </div>
      ) : null}
    </Modal>
  );
}
