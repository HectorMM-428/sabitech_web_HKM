/**
 * Cliente HTTP de SABITECH WEB.
 *
 * - Todas las llamadas pasan por aquí: así el formato de error, la cookie de sesión
 *   y la cabecera CSRF se tratan en un solo sitio.
 * - La sesión es una cookie HttpOnly de Laravel Sanctum: JavaScript no la lee, el
 *   navegador la envía sola porque front y API comparten dominio.
 * - Para peticiones que modifican datos se reenvía la cookie XSRF-TOKEN en la
 *   cabecera X-XSRF-TOKEN (protección CSRF de Sanctum).
 */
import type { ApiErrorBody } from './types';

const BASE_URL: string = import.meta.env.VITE_API_BASE_URL ?? '/api/v1';

/** Código propio del cliente para fallos de red: nunca viene del servidor. */
export const NETWORK_ERROR = 'NETWORK_ERROR';

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly fields: Record<string, string[]> | null;

  constructor(status: number, code: string, message: string, fields: Record<string, string[]> | null = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.fields = fields;
  }

  get isNotFound(): boolean {
    return this.status === 404;
  }

  get isUnauthenticated(): boolean {
    return this.status === 401;
  }
}

function readCookie(name: string): string | null {
  const match = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}

export async function apiFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const method = (init.method ?? 'GET').toUpperCase();
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');

  if (init.body !== undefined && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  if (method !== 'GET' && method !== 'HEAD') {
    const xsrf = readCookie('XSRF-TOKEN');
    if (xsrf) headers.set('X-XSRF-TOKEN', xsrf);
  }

  let response: Response;
  try {
    response = await fetch(`${BASE_URL}${path}`, { ...init, method, headers, credentials: 'same-origin' });
  } catch (error) {
    if (isAbortError(error)) throw error;
    throw new ApiError(0, NETWORK_ERROR, 'No se ha podido conectar con el servidor. Comprueba tu conexión e inténtalo de nuevo.');
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const body: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const error = (body as ApiErrorBody | null)?.error;
    throw new ApiError(
      response.status,
      error?.code ?? 'SERVER_ERROR',
      error?.message ?? 'Ha ocurrido un error inesperado. Inténtalo de nuevo más tarde.',
      (error?.fields as Record<string, string[]> | null | undefined) ?? null,
    );
  }

  return body as T;
}
