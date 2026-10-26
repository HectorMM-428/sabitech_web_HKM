import { describe, expect, it } from 'vitest';
import { eventDateKey, formatDay, formatTime, formatTimeRange } from './datetime';

describe('fechas en la zona de las jornadas (Europe/Madrid)', () => {
  it('muestra la hora de Madrid aunque la API envíe UTC', () => {
    // 09:00 UTC en invierno = 10:00 en Madrid (UTC+1)
    expect(formatTime('2027-02-24T09:00:00Z')).toBe('10:00');
  });

  it('respeta el horario de verano', () => {
    // 08:00 UTC en junio = 10:00 en Madrid (UTC+2)
    expect(formatTime('2027-06-10T08:00:00Z')).toBe('10:00');
  });

  it('formatea el día y el rango horario en español', () => {
    expect(formatDay('2027-02-24T10:00:00+01:00')).toBe('miércoles, 24 de febrero');
    expect(formatTimeRange('2027-02-24T10:00:00+01:00', '2027-02-24T10:45:00+01:00')).toBe('10:00–10:45');
  });

  it('calcula la fecha del evento en Madrid, no en UTC', () => {
    // 23:30 UTC del 23 = 00:30 del 24 en Madrid
    expect(eventDateKey('2027-02-23T23:30:00Z')).toBe('2027-02-24');
  });
});
