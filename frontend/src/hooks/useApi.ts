import { useCallback, useEffect, useState } from 'react';
import { ApiError } from '../api/client';

export type ApiState<T> =
  | { status: 'loading' }
  | { status: 'success'; data: T }
  | { status: 'error'; error: ApiError };

/**
 * Carga datos de la API y expone los tres estados que toda vista debe pintar
 * (cargando, éxito, error). Cancela la petición anterior si cambian los parámetros
 * o si el componente desaparece, para no mostrar resultados viejos.
 */
export function useApi<T>(load: (signal: AbortSignal) => Promise<T>, key: string) {
  const [state, setState] = useState<ApiState<T>>({ status: 'loading' });
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    setState({ status: 'loading' });

    load(controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setState({ status: 'success', data });
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
