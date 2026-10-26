import type { ApiError } from '../../api/client';
import { NETWORK_ERROR } from '../../api/client';
import type { CheckInResult } from '../../api/types';
import { formatTime } from '../../lib/datetime';

export type CheckInOutcome = { kind: 'ok'; result: CheckInResult } | { kind: 'error'; error: ApiError };

/**
 * Resultado de una validación, grande y legible a un metro de distancia. Siempre
 * color + icono + texto. Un fallo de red NUNCA se muestra como registrado (§4.5).
 */
export function CheckInResultPanel({ outcome }: { outcome: CheckInOutcome | null }) {
  return (
    <div className="checkin-result-region" role="status" aria-live="polite" aria-label="Resultado de la validación">
      {outcome ? <Panel outcome={outcome} /> : null}
    </div>
  );
}

function Panel({ outcome }: { outcome: CheckInOutcome }) {
  if (outcome.kind === 'ok') {
    const r = outcome.result;
    if (r.result === 'REGISTERED') {
      return (
        <div className="checkin-result checkin-result--ok">
          <p className="checkin-result__title">
            <span aria-hidden="true">✓ </span>Registrado: {r.attendee_name}
          </p>
          <p>
            {r.talk_title} · {formatTime(r.validated_at)} · {r.method === 'manual' ? 'registro manual' : 'QR'}
          </p>
          <p className="field__hint">Comprueba que la persona coincide con el nombre.</p>
        </div>
      );
    }
    return (
      <div className="checkin-result checkin-result--warning">
        <p className="checkin-result__title">
          <span aria-hidden="true">! </span>Ya registrado a las {formatTime(r.validated_at)}
        </p>
        <p>
          {r.attendee_name} · {r.talk_title}. No se ha creado otro registro.
        </p>
      </div>
    );
  }

  const network = outcome.error.code === NETWORK_ERROR || outcome.error.status >= 500;
  return (
    <div className="checkin-result checkin-result--error">
      <p className="checkin-result__title">
        <span aria-hidden="true">✕ </span>
        {network ? 'No se pudo validar' : 'Código rechazado'}
      </p>
      <p>{network ? 'Problema de conexión. Vuelve a escanear; si persiste, usa el registro manual.' : outcome.error.message}</p>
    </div>
  );
}
