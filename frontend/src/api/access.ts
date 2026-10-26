/** Acreditación (A12) y control de acceso (A13–A16). */
import { apiFetch } from './client';
import type { CheckInResult, Credential, Single, StaffReservationRow, StaffTalk } from './types';

export function getCredential(reservationId: number, signal?: AbortSignal): Promise<Single<Credential>> {
  return apiFetch<Single<Credential>>(`/reservations/${reservationId}/credential`, { signal, cache: 'no-store' });
}

export function listStaffTalks(date: string, signal?: AbortSignal): Promise<{ data: StaffTalk[] }> {
  return apiFetch<{ data: StaffTalk[] }>(`/staff/talks?date=${encodeURIComponent(date)}`, { signal });
}

export function checkIn(token: string, talkId: number): Promise<Single<CheckInResult>> {
  return apiFetch<Single<CheckInResult>>('/checkins', { method: 'POST', body: JSON.stringify({ token, talk_id: talkId }) });
}

export function searchTalkReservations(talkId: number, q: string, signal?: AbortSignal): Promise<{ data: StaffReservationRow[] }> {
  return apiFetch<{ data: StaffReservationRow[] }>(`/staff/talks/${talkId}/reservations?q=${encodeURIComponent(q)}`, { signal });
}

export function manualCheckIn(reservationId: number, reason: string): Promise<Single<CheckInResult>> {
  return apiFetch<Single<CheckInResult>>('/checkins/manual', {
    method: 'POST',
    body: JSON.stringify({ reservation_id: reservationId, reason }),
  });
}
