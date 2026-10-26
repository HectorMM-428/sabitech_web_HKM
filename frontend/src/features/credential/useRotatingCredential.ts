import { useEffect, useRef, useState } from 'react';
import { getCredential } from '../../api/access';
import { ApiError } from '../../api/client';

/** Se pide el siguiente token 2 s antes de que caduque el actual (diseño, sección 5). */
export const REFRESH_BEFORE_MS = 2000;
/** Si falla la red, se reintenta cada 3 s. */
export const RETRY_AFTER_ERROR_MS = 3000;

export type CredentialState =
  | { phase: 'loading' }
  /** Hay un token vigente según la hora del servidor. */
  | { phase: 'active'; token: string; secondsLeft: number; reconnecting: boolean }
  /** El último token ha caducado y no ha llegado otro: no se muestra ningún QR. */
  | { phase: 'expired' }
  /** El servidor no da credencial (asistencia registrada, fuera de horario, reserva cancelada…). */
  | { phase: 'blocked'; error: ApiError };

/**
 * Renueva el QR cada 30 s pidiendo un token nuevo al servidor.
 *
 * - La validez la decide el servidor: aquí solo se muestra la cuenta atrás.
 * - La cuenta atrás usa la hora del servidor (server_time) y no la del móvil,
 *   así un reloj adelantado o atrasado no cambia nada.
 * - Si la red falla, el QR deja de mostrarse cuando caduca: nunca se enseña un
 *   código vencido como si fuera válido.
 */
export function useRotatingCredential(reservationId: number): CredentialState {
  const [state, setState] = useState<CredentialState>({ phase: 'loading' });
  const current = useRef<{ token: string; expiresAt: number; offset: number } | null>(null);
  const reconnecting = useRef(false);

  // Petición de tokens.
  useEffect(() => {
    let cancelled = false;
    let timer: number | undefined;
    const controller = new AbortController();

    async function fetchToken() {
      try {
        const res = await getCredential(reservationId, controller.signal);
        if (cancelled) return;
        const offset = Date.parse(res.data.server_time) - Date.now();
        const expiresAt = Date.parse(res.data.expires_at);
        current.current = { token: res.data.token, expiresAt, offset };
        reconnecting.current = false;
        setState({ phase: 'active', token: res.data.token, secondsLeft: secondsLeft(expiresAt, offset), reconnecting: false });

        const wait = expiresAt - REFRESH_BEFORE_MS - (Date.now() + offset);
        timer = window.setTimeout(fetchToken, Math.max(1000, wait));
      } catch (e) {
        if (cancelled || (e instanceof DOMException && e.name === 'AbortError')) return;
        const error = e instanceof ApiError ? e : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.');
        // Respuestas definitivas del servidor: no tiene sentido seguir pidiendo tokens.
        if ([401, 403, 404, 409].includes(error.status)) {
          current.current = null;
          setState({ phase: 'blocked', error });
          return;
        }
        // Fallo de red o del servidor: se reintenta; el QR actual sigue hasta que caduque.
        reconnecting.current = true;
        timer = window.setTimeout(fetchToken, RETRY_AFTER_ERROR_MS);
      }
    }

    fetchToken();
    return () => {
      cancelled = true;
      controller.abort();
      window.clearTimeout(timer);
    };
  }, [reservationId]);

  // Cuenta atrás cada segundo, con la hora del servidor.
  useEffect(() => {
    const id = window.setInterval(() => {
      const c = current.current;
      if (!c) return;
      const left = secondsLeft(c.expiresAt, c.offset);
      if (left <= 0) {
        current.current = null;
        setState({ phase: 'expired' });
      } else {
        setState({ phase: 'active', token: c.token, secondsLeft: left, reconnecting: reconnecting.current });
      }
    }, 1000);
    return () => window.clearInterval(id);
  }, []);

  return state;
}

function secondsLeft(expiresAt: number, offset: number): number {
  return Math.max(0, Math.ceil((expiresAt - (Date.now() + offset)) / 1000));
}
