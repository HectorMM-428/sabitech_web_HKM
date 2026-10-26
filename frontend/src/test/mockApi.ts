import { vi } from 'vitest';
import type { User } from '../api/types';
import { jsonResponse } from './fixtures';

type Handler = (init: RequestInit | undefined, url: URL) => Response | Promise<Response>;

/**
 * Simula la API por método y ruta, p. ej. { 'GET /api/v1/talks': () => jsonResponse(...) }.
 * Por defecto: /auth/me responde 401 (visitante) y /sanctum/csrf-cookie 204.
 * Cualquier otra ruta no declarada hace fallar la prueba, para no ocultar llamadas inesperadas.
 */
export function mockApi(routes: Record<string, Handler>, options: { user?: User | null } = {}) {
  const defaults: Record<string, Handler> = {
    'GET /api/v1/auth/me': () =>
      options.user
        ? jsonResponse({ data: options.user })
        : jsonResponse({ error: { code: 'UNAUTHENTICATED', message: 'Tu sesión ha caducado.', fields: null } }, 401),
    'GET /sanctum/csrf-cookie': () => new Response(null, { status: 204 }),
  };
  const table = { ...defaults, ...routes };

  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = new URL(String(input), 'http://localhost');
    const method = (init?.method ?? 'GET').toUpperCase();
    const handler = table[`${method} ${url.pathname}${url.search}`] ?? table[`${method} ${url.pathname}`];
    if (!handler) throw new Error(`Llamada no simulada: ${method} ${url.pathname}${url.search}`);
    return handler(init, url);
  });

  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

/** Llamadas a la API hechas a una ruta concreta (sin contar /auth/me ni csrf). */
export function callsTo(fetchMock: ReturnType<typeof vi.fn>, pathPrefix: string) {
  return fetchMock.mock.calls.filter(([input]) => new URL(String(input), 'http://localhost').pathname.startsWith(pathPrefix));
}

export const attendee: User = { id: 21, name: 'Lucía Ferrer', email: 'lucia@sabitech.test', role: 'attendee' };
export const staffUser: User = { id: 2, name: 'Sergio Acceso', email: 'staff@sabitech.test', role: 'staff' };
export const adminUser: User = { id: 1, name: 'Ana Organizadora', email: 'admin@sabitech.test', role: 'admin' };
