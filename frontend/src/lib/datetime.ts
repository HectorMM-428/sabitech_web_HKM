/**
 * Fechas y horas siempre en la zona de las jornadas (Europe/Madrid),
 * con independencia de la zona del dispositivo. La API envía ISO 8601 con desfase.
 */
export const EVENT_TIME_ZONE = 'Europe/Madrid';
const LOCALE = 'es-ES';

const dayFormatter = new Intl.DateTimeFormat(LOCALE, {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  timeZone: EVENT_TIME_ZONE,
});

const timeFormatter = new Intl.DateTimeFormat(LOCALE, {
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
  timeZone: EVENT_TIME_ZONE,
});

const keyFormatter = new Intl.DateTimeFormat('en-CA', {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  timeZone: EVENT_TIME_ZONE,
});

/** «martes, 24 de febrero» */
export function formatDay(iso: string): string {
  return dayFormatter.format(new Date(iso));
}

/** «10:00» */
export function formatTime(iso: string): string {
  return timeFormatter.format(new Date(iso));
}

/** «10:00–10:45» */
export function formatTimeRange(startIso: string, endIso: string): string {
  return `${formatTime(startIso)}–${formatTime(endIso)}`;
}

/** Día del evento en formato YYYY-MM-DD, para agrupar y para el filtro `date`. */
export function eventDateKey(iso: string): string {
  return keyFormatter.format(new Date(iso));
}
