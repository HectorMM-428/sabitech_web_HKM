import { useState } from 'react';
import { Link, useLocation } from 'react-router';
import { ApiError } from '../../api/client';
import { createReservation, listMyReservations } from '../../api/reservations';
import type { Reservation, TalkDetail } from '../../api/types';
import { useAuth } from '../../auth/useAuth';
import { Button, FormAlert } from '../../components/ui/Form';
import { useApi } from '../../hooks/useApi';

interface Props {
  talk: TalkDetail;
  /** Pide a la página que vuelva a cargar la charla (plazas actualizadas desde el servidor). */
  onChanged: () => void;
}

/**
 * Zona de reserva del detalle de charla. El navegador no decide si hay plaza:
 * envía la petición y muestra lo que responde el servidor (§4.7 del enunciado).
 */
export function ReserveAction({ talk, onChanged }: Props) {
  const { status, user } = useAuth();
  const location = useLocation();

  if (status === 'loading') return null;

  if (status === 'guest' || !user) {
    const next = encodeURIComponent(location.pathname);
    return (
      <div className="reserve-box">
        <p>Inicia sesión para reservar tu plaza.</p>
        <div className="reserve-box__actions">
          <Link className="button button--primary" to={`/login?next=${next}`}>
            Entrar para reservar
          </Link>
          <Link className="button button--secondary" to={`/registro?next=${next}`}>
            Crear cuenta
          </Link>
        </div>
      </div>
    );
  }

  if (user.role !== 'attendee') {
    return <p className="reserve-box muted">Las reservas son para asistentes. Tu perfil es de {user.role === 'admin' ? 'organización' : 'personal de acceso'}.</p>;
  }

  return <AttendeeReserve talk={talk} onChanged={onChanged} />;
}

function AttendeeReserve({ talk, onChanged }: Props) {
  const mine = useApi((signal) => listMyReservations('confirmed', signal), `mine-confirmed-${talk.id}`);
  const [submitting, setSubmitting] = useState(false);
  const [created, setCreated] = useState<Reservation | null>(null);
  const [error, setError] = useState<ApiError | null>(null);

  const existing = mine.state.status === 'success' ? mine.state.data.data.find((r) => r.talk.id === talk.id) : undefined;

  async function onReserve() {
    setSubmitting(true);
    setError(null);
    try {
      const res = await createReservation(talk.id);
      setCreated(res.data);
      mine.reload();
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.'));
    } finally {
      setSubmitting(false);
      // Éxito o error (p. ej. otra persona se llevó la última plaza): las plazas se vuelven a pedir al servidor.
      onChanged();
    }
  }

  if (created) {
    return (
      <div className="reserve-box">
        <FormAlert tone="success">
          <p>
            <strong>Reserva confirmada.</strong> Tu referencia es <span className="mono">{created.reference}</span>.
          </p>
          <p>Te enviaremos un correo de confirmación. Puedes consultarla en <Link to="/mis-reservas">Mis reservas</Link>.</p>
        </FormAlert>
      </div>
    );
  }

  if (existing) {
    return (
      <div className="reserve-box">
        <FormAlert tone="info">
          <p>
            Ya tienes plaza en esta charla (referencia <span className="mono">{existing.reference}</span>).{' '}
            <Link to="/mis-reservas">Ver mis reservas</Link>
          </p>
        </FormAlert>
      </div>
    );
  }

  const full = talk.available_seats <= 0;

  return (
    <div className="reserve-box">
      {error ? <FormAlert>{error.message}</FormAlert> : null}
      {full ? (
        <p className="muted">Esta charla está completa. Si alguien cancela, la plaza volverá a estar disponible aquí.</p>
      ) : (
        <Button onClick={onReserve} loading={submitting} loadingText="Reservando…" disabled={mine.state.status === 'loading'}>
          Reservar plaza
        </Button>
      )}
    </div>
  );
}
