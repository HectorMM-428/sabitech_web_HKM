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

const partsFormatter = new Intl.DateTimeFormat('en-GB', {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
  timeZone: EVENT_TIME_ZONE,
});

function madridParts(date: Date) {
  const p = Object.fromEntries(partsFormatter.formatToParts(date).map((x) => [x.type, x.value]));
  return { y: +p.year, mo: +p.month, d: +p.day, h: +p.hour, mi: +p.minute };
}

/** ISO → valor de un <input type="datetime-local"> en hora de Madrid («2027-02-24T10:00»). */
export function toEventLocalInput(iso: string): string {
  const { y, mo, d, h, mi } = madridParts(new Date(iso));
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${y}-${pad(mo)}-${pad(d)}T${pad(h)}:${pad(mi)}`;
}

/**
 * Valor de un <input type="datetime-local"> interpretado como hora de Madrid → ISO UTC.
 * Así la organización escribe la hora del evento aunque su equipo esté en otra zona.
 */
export function fromEventLocalInput(local: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(local);
  if (!m) return '';
  const [y, mo, d, h, mi] = m.slice(1).map(Number);
  const wanted = Date.UTC(y, mo - 1, d, h, mi);
  // Desfase de Madrid en ese instante (cambia con el horario de verano): se corrige dos veces.
  let guess = wanted;
  for (let i = 0; i < 2; i++) {
    const p = madridParts(new Date(guess));
    const shown = Date.UTC(p.y, p.mo - 1, p.d, p.h, p.mi);
    guess += wanted - shown;
  }
  return new Date(guess).toISOString().replace('.000Z', 'Z');
}
