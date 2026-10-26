interface AvailabilityProps {
  available: number;
  capacity: number;
}

/** Umbral a partir del cual se avisa de «últimas plazas». */
export const LOW_SEATS_THRESHOLD = 5;

/**
 * Plazas disponibles. El estado se expresa con texto e icono, no solo con color
 * (WCAG 1.4.1). El número viene siempre de la API: nunca se calcula en el cliente.
 */
export function Availability({ available, capacity }: AvailabilityProps) {
  if (available <= 0) {
    return (
      <span className="availability availability--full">
        <span aria-hidden="true">✕</span> Completa · {capacity} plazas
      </span>
    );
  }

  if (available <= LOW_SEATS_THRESHOLD) {
    return (
      <span className="availability availability--low">
        <span aria-hidden="true">!</span> {available === 1 ? 'Última plaza' : `Últimas ${available} plazas`} de {capacity}
      </span>
    );
  }

  return (
    <span className="availability availability--ok">
      <span aria-hidden="true">✓</span> {available} de {capacity} plazas libres
    </span>
  );
}
