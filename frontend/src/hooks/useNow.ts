import { useEffect, useState } from 'react';

/**
 * Hora actual (ms) como estado de React, renovada cada `intervalMs`.
 * Sirve para decidir en pantalla si una charla ya empezó sin llamar a Date.now()
 * durante el render. La decisión que cuenta la sigue tomando el servidor.
 */
export function useNow(intervalMs = 60_000): number {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    const id = window.setInterval(() => setNow(Date.now()), intervalMs);
    return () => window.clearInterval(id);
  }, [intervalMs]);

  return now;
}
