import { useEffect } from 'react';
import { Link, useParams } from 'react-router';
import { getMyReservation } from '../../api/reservations';
import type { Reservation } from '../../api/types';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { formatDay, formatTime, formatTimeRange } from '../../lib/datetime';
import { QrCode } from './QrCode';
import { useRotatingCredential, type CredentialState } from './useRotatingCredential';

const TTL_SECONDS = 30;

/** A12 · Acreditación QR del asistente (R16, §4.5 y §4.7 del enunciado). */
export function CredentialPage() {
  const id = Number(useParams().reservationId);
  const reservation = useApi((signal) => getMyReservation(id, signal), `credential-${id}`);
  useDocumentTitle('Acreditación');

  return (
    <div className="page page--narrow">
      <p className="back-link">
        <Link to="/mis-reservas">← Mis reservas</Link>
      </p>
      {reservation.state.status === 'loading' && <LoadingBlocks label="Cargando la reserva…" count={1} height={360} />}
      {reservation.state.status === 'error' &&
        (reservation.state.error.isNotFound ? (
          <EmptyState title="No encontramos esta reserva">
            <Link to="/mis-reservas">Vuelve a tus reservas</Link>
          </EmptyState>
        ) : (
          <ErrorState error={reservation.state.error} onRetry={reservation.reload} />
        ))}
      {reservation.state.status === 'success' && (
        <Credential reservation={reservation.state.data.data} onAttended={reservation.reload} />
      )}
    </div>
  );
}

function Credential({ reservation, onAttended }: { reservation: Reservation; onAttended: () => void }) {
  const { talk } = reservation;

  if (reservation.display_status === 'attended') {
    return (
      <section className="credential" aria-labelledby="credential-title">
        <h1 id="credential-title">{talk.title}</h1>
        <div className="credential__done" role="status">
          <span aria-hidden="true">✓</span> Asistencia registrada
          {reservation.attended_at ? ` a las ${formatTime(reservation.attended_at)}` : ''}.
        </div>
      </section>
    );
  }

  if (reservation.status !== 'confirmed') {
    return <EmptyState title="Esta reserva está cancelada">No tiene acreditación.</EmptyState>;
  }

  return <ActiveCredential reservation={reservation} onAttended={onAttended} />;
}

function ActiveCredential({ reservation, onAttended }: { reservation: Reservation; onAttended: () => void }) {
  const { talk } = reservation;
  const state = useRotatingCredential(reservation.id);

  // Si el servidor dice que la asistencia ya está registrada, se recarga la reserva para mostrar la hora.
  const attended = state.phase === 'blocked' && state.error.code === 'ATTENDED';
  useEffect(() => {
    if (attended) onAttended();
  }, [attended, onAttended]);

  return (
    <section className="credential" aria-labelledby="credential-title">
      <h1 id="credential-title">{talk.title}</h1>
      <p className="muted">
        {formatDay(talk.starts_at)} · <span className="mono">{formatTimeRange(talk.starts_at, talk.ends_at)}</span> · {talk.room.name}
      </p>

      <div className="credential__card">
        {state.phase === 'active' ? (
          <>
            {/* Etiqueta fija: no cambia al renovarse, así el lector de pantalla no lo anuncia cada 30 s. */}
            <QrCode value={state.token} label={`Código QR de acceso a ${talk.title}`} />
            <div className="credential__timer" aria-hidden="true">
              <div className="credential__bar" style={{ width: `${(state.secondsLeft / TTL_SECONDS) * 100}%` }} />
            </div>
            <p className="credential__countdown">Se renueva en {state.secondsLeft} s</p>
          </>
        ) : (
          <div className="credential__placeholder">{placeholderText(state)}</div>
        )}
        <p className="credential__ref">
          Referencia <span className="mono">{reservation.reference}</span>
        </p>
      </div>

      {/* Única región viva: anuncia cambios de estado, nunca cada renovación ni cada segundo. */}
      <div className="credential__status" role="status" aria-live="polite" aria-label="Estado del código">
        {statusMessage(state)}
      </div>

      <p className="field__hint">
        Muestra este código al personal de acceso con el brillo de la pantalla alto. Si no puedes usarlo, di tu nombre o la referencia en la entrada.
      </p>
    </section>
  );
}

function placeholderText(state: CredentialState): string {
  switch (state.phase) {
    case 'loading':
      return 'Generando código…';
    case 'expired':
      return 'Código caducado';
    case 'blocked':
      return 'Código no disponible';
    default:
      return 'Generando código…';
  }
}

function statusMessage(state: CredentialState): string {
  if (state.phase === 'expired') {
    return 'Sin conexión: el código ha caducado. Se mostrará uno nuevo en cuanto vuelva la conexión.';
  }
  if (state.phase === 'blocked') {
    if (state.error.code === 'OUTSIDE_WINDOW') return state.error.message;
    if (state.error.code === 'ATTENDED') return 'Tu asistencia ya está registrada.';
    return state.error.message;
  }
  if (state.phase === 'active' && state.reconnecting) {
    return 'Conexión inestable: intentando renovar el código.';
  }
  return '';
}

