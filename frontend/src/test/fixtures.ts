import type { Paginated, TalkDetail, TalkSummary } from '../api/types';

export function makeTalk(overrides: Partial<TalkDetail> = {}): TalkDetail {
  return {
    id: 7,
    title: 'Accesibilidad web más allá del contraste',
    description: 'Cómo probar una interfaz con teclado y lector de pantalla.',
    speaker: { id: 3, name: 'Marta Ibáñez', affiliation: 'Estudio Faro', bio: null, photo_url: null, photo_alt: null },
    room: { id: 1, name: 'Aula Magna', location_details: 'Planta baja' },
    starts_at: '2027-02-24T10:00:00+01:00',
    ends_at: '2027-02-24T10:45:00+01:00',
    capacity: 40,
    available_seats: 12,
    status: 'published',
    ...overrides,
  };
}

export function page(talks: TalkSummary[], meta: Partial<Paginated<TalkSummary>['meta']> = {}): Paginated<TalkSummary> {
  return {
    data: talks,
    meta: { current_page: 1, per_page: 20, total: talks.length, last_page: 1, ...meta },
  };
}

export function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}
