import { afterEach, describe, expect, it, vi } from 'vitest';
import { jsonResponse } from '../test/fixtures';
import { ApiError, apiFetch, NETWORK_ERROR } from './client';

describe('apiFetch', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
  });

  it('devuelve el cuerpo JSON en una respuesta correcta', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: { ok: true } })));
    await expect(apiFetch('/talks')).resolves.toEqual({ data: { ok: true } });
  });

  it('convierte el formato de error de la API en ApiError con su código', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse({ error: { code: 'TALK_FULL', message: 'La charla no tiene plazas disponibles.', fields: null } }, 409),
      ),
    );
    const error = await apiFetch('/reservations', { method: 'POST', body: '{}' }).catch((e: unknown) => e);
    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 409, code: 'TALK_FULL', message: 'La charla no tiene plazas disponibles.' });
  });

  it('un fallo de red nunca se presenta como éxito', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    const error = await apiFetch('/talks').catch((e: unknown) => e);
    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 0, code: NETWORK_ERROR });
  });

  it('envía la cabecera X-XSRF-TOKEN solo en peticiones que modifican datos', async () => {
    document.cookie = 'XSRF-TOKEN=abc%3D123';
    const fetchMock = vi.fn().mockImplementation(() => Promise.resolve(jsonResponse({ data: null })));
    vi.stubGlobal('fetch', fetchMock);

    await apiFetch('/talks');
    await apiFetch('/reservations', { method: 'POST', body: '{}' });

    const getHeaders = fetchMock.mock.calls[0][1].headers as Headers;
    const postHeaders = fetchMock.mock.calls[1][1].headers as Headers;
    expect(getHeaders.has('X-XSRF-TOKEN')).toBe(false);
    expect(postHeaders.get('X-XSRF-TOKEN')).toBe('abc=123');
    expect(postHeaders.get('Content-Type')).toBe('application/json');
  });

  it('una respuesta sin cuerpo JSON se trata como error genérico del servidor', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>500</html>', { status: 500 })));
    await expect(apiFetch('/talks')).rejects.toMatchObject({ status: 500, code: 'SERVER_ERROR' });
  });
});
