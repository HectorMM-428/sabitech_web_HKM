import type { Reservation } from '../api/types';
import { makeTalk } from './fixtures';

export function makeReservation(overrides: Partial<Reservation> = {}): Reservation {
  return {
    id: 1842,
    reference: 'SBT-7K3Q9X',
    status: 'confirmed',
    display_status: 'confirmed',
    confirmed_at: '2027-02-10T18:22:04+01:00',
    cancelled_at: null,
    attended_at: null,
    email_status: 'sent',
    talk: makeTalk({ starts_at: '2099-02-24T10:00:00+01:00', ends_at: '2099-02-24T10:45:00+01:00' }),
    ...overrides,
  };
}
