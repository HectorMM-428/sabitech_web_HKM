/** Panel de organización (A17–A28 y A30). Solo para administradores. */
import { apiFetch } from './client';
import type {
  AdminReservationRow,
  AdminStats,
  Attendance,
  Notification,
  NotificationStatus,
  Paginated,
  Room,
  RoomInput,
  Single,
  Speaker,
  SpeakerInput,
  TalkDetail,
  TalkInput,
  TalkStatus,
  User,
  UserRole,
} from './types';

/** Construye «?a=1&b=2» sin parámetros vacíos. */
export function query(params: Record<string, string | number | undefined | null>): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') search.set(key, String(value));
  }
  const s = search.toString();
  return s ? `?${s}` : '';
}

const json = (method: string, body?: unknown): RequestInit => ({ method, body: body === undefined ? undefined : JSON.stringify(body) });

// --- Indicadores -------------------------------------------------------------

export const getStats = (signal?: AbortSignal) => apiFetch<Single<AdminStats>>('/admin/stats', { signal });

// --- Charlas -------------------------------------------------------------------

export const listAdminTalks = (filters: { status?: TalkStatus | ''; page?: number }, signal?: AbortSignal) =>
  apiFetch<Paginated<TalkDetail>>(`/admin/talks${query(filters)}`, { signal });

export const getAdminTalk = (id: number, signal?: AbortSignal) => apiFetch<Single<TalkDetail>>(`/admin/talks/${id}`, { signal });

export const createTalk = (input: TalkInput) => apiFetch<Single<TalkDetail>>('/admin/talks', json('POST', input));

export const updateTalk = (id: number, input: TalkInput) => apiFetch<Single<TalkDetail>>(`/admin/talks/${id}`, json('PUT', input));

export const changeTalkStatus = (id: number, status: TalkStatus) =>
  apiFetch<Single<TalkDetail>>(`/admin/talks/${id}/status`, json('PATCH', { status }));

// --- Ponentes y salas --------------------------------------------------------------

export const listSpeakers = (signal?: AbortSignal) => apiFetch<{ data: Speaker[] }>('/admin/speakers', { signal });
export const saveSpeaker = (input: SpeakerInput, id?: number) =>
  apiFetch<Single<Speaker>>(id ? `/admin/speakers/${id}` : '/admin/speakers', json(id ? 'PUT' : 'POST', input));
export const deleteSpeaker = (id: number) => apiFetch<void>(`/admin/speakers/${id}`, json('DELETE'));

export const listRooms = (signal?: AbortSignal) => apiFetch<{ data: Room[] }>('/admin/rooms', { signal });
export const saveRoom = (input: RoomInput, id?: number) =>
  apiFetch<Single<Room>>(id ? `/admin/rooms/${id}` : '/admin/rooms', json(id ? 'PUT' : 'POST', input));
export const deleteRoom = (id: number) => apiFetch<void>(`/admin/rooms/${id}`, json('DELETE'));

// --- Actividad -------------------------------------------------------------------

export interface ReservationFilters {
  talk_id?: number | '';
  status?: 'confirmed' | 'cancelled' | 'attended' | '';
  q?: string;
  page?: number;
}

export const listAdminReservations = (filters: ReservationFilters, signal?: AbortSignal) =>
  apiFetch<Paginated<AdminReservationRow>>(`/admin/reservations${query({ ...filters })}`, { signal });

export const listAttendances = (filters: { talk_id?: number | ''; method?: 'qr' | 'manual' | ''; page?: number }, signal?: AbortSignal) =>
  apiFetch<Paginated<Attendance>>(`/admin/attendances${query({ ...filters })}`, { signal });

export const listNotifications = (filters: { status?: NotificationStatus | ''; page?: number }, signal?: AbortSignal) =>
  apiFetch<Paginated<Notification>>(`/admin/notifications${query(filters)}`, { signal });

export const retryNotification = (id: number) => apiFetch<Single<Notification>>(`/admin/notifications/${id}/retry`, json('POST'));

// --- Usuarios --------------------------------------------------------------------

export const listUsers = (filters: { q?: string; role?: UserRole | ''; page?: number }, signal?: AbortSignal) =>
  apiFetch<Paginated<User>>(`/admin/users${query(filters)}`, { signal });

export const changeRole = (id: number, role: UserRole) => apiFetch<Single<User>>(`/admin/users/${id}/role`, json('PATCH', { role }));
