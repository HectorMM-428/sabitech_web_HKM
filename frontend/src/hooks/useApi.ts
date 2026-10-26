import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError } from '../api/client';

export type ApiState<T> =
  | { status: 'loading' }
  | { status: 'success'; data: T; refreshing: boolean }
  | { status: 'error'; error: ApiError };

/**
 * Carga datos de la API y expone los estados que toda vista debe pintar
 * (cargando, éxito, error). Cancela la petición anterior si cambian los parámetros
 * o si el componente desaparece, para no mostrar resultados viejos.
 *
 * `reload()` vuelve a pedir los mismos datos sin quitar de la pantalla los que ya
 * había (refreshing = true): así una actualización tras reservar no hace parpadear la vista.
 */
export function useApi<T>(load: (signal: AbortSignal) => Promise<T>, key: string) {
  const [state, setState] = useState<ApiState<T>>({ status: 'loading' });
  const [attempt, setAttempt] = useState(0);
  const lastKey = useRef<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    const sameKey = lastKey.current === key;
    lastKey.current = key;

    setState((prev) => (sameKey && prev.status === 'success' ? { ...prev, refreshing: true } : { status: 'loading' }));

    load(controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setState({ status: 'success', data, refreshing: false });
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted) return;
        setState({
          status: 'error',
          error: error instanceof ApiError ? error : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.'),
        });
      });

    return () => controller.abort();
    // `load` cambia en cada render; la clave resume sus parámetros.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, attempt]);

  const reload = useCallback(() => setAttempt((n) => n + 1), []);

  return { state, reload };
}
