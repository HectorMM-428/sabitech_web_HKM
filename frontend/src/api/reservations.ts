/** Operaciones A08–A11 (reservas del asistente). */
import { apiFetch } from './client';
import type { Reservation, Single } from './types';

export function listMyReservations(status?: 'confirmed' | 'cancelled', signal?: AbortSignal): Promise<{ data: Reservation[] }> {
  return apiFetch<{ data: Reservation[] }>(`/me/reservations${status ? `?status=${status}` : ''}`, { signal });
}

export function getMyReservation(id: number, signal?: AbortSignal): Promise<Single<Reservation>> {
  return apiFetch<Single<Reservation>>(`/me/reservations/${id}`, { signal });
}

export function createReservation(talkId: number): Promise<Single<Reservation>> {
  return apiFetch<Single<Reservation>>('/reservations', { method: 'POST', body: JSON.stringify({ talk_id: talkId }) });
}

export function cancelReservation(id: number): Promise<Single<Reservation>> {
  return apiFetch<Single<Reservation>>(`/reservations/${id}/cancel`, { method: 'POST' });
}
