/** Operaciones A01 y A02 del contrato (catálogo público). */
import { apiFetch } from './client';
import type { Paginated, Single, TalkDetail, TalkSummary } from './types';

export interface TalkFilters {
  date?: string;
  q?: string;
  page?: number;
}

export function listTalks(filters: TalkFilters, signal?: AbortSignal): Promise<Paginated<TalkSummary>> {
  const params = new URLSearchParams();
  if (filters.date) params.set('date', filters.date);
  if (filters.q) params.set('q', filters.q);
  if (filters.page && filters.page > 1) params.set('page', String(filters.page));
  const query = params.toString();
  return apiFetch<Paginated<TalkSummary>>(`/talks${query ? `?${query}` : ''}`, { signal });
}

export function getTalk(id: number, signal?: AbortSignal): Promise<Single<TalkDetail>> {
  return apiFetch<Single<TalkDetail>>(`/talks/${id}`, { signal });
}
