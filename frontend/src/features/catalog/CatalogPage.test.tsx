import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { routes } from '../../routes';
import { jsonResponse, makeTalk, page } from '../../test/fixtures';
import { callsTo, mockApi } from '../../test/mockApi';

function renderAt(path: string) {
  const router = createMemoryRouter(routes, { initialEntries: [path] });
  render(<RouterProvider router={router} />);
  return router;
}

describe('Programa de charlas (A01)', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('muestra un estado de carga y después las charlas que devuelve la API', async () => {
    mockApi({
      'GET /api/v1/talks': () => jsonResponse(page([makeTalk(), makeTalk({ id: 8, title: 'Laravel y colas', available_seats: 0 })])),
    });
    renderAt('/');

    expect(screen.getByText('Cargando charlas…')).toBeInTheDocument();
    expect(await screen.findByRole('link', { name: 'Accesibilidad web más allá del contraste' })).toHaveAttribute('href', '/charlas/7');
    expect(screen.getByText('2 charlas')).toBeInTheDocument();
    expect(screen.getByText(/12 de 40 plazas libres/)).toBeInTheDocument();
    expect(screen.getByText(/Completa/)).toBeInTheDocument();
  });

  it('muestra un estado vacío si no hay charlas publicadas', async () => {
    mockApi({ 'GET /api/v1/talks': () => jsonResponse(page([])) });
    renderAt('/');
    expect(await screen.findByText('Todavía no hay charlas publicadas')).toBeInTheDocument();
  });

  it('si la API no responde, muestra el error y permite reintentar', async () => {
    let attempts = 0;
    const fetchMock = mockApi({
      'GET /api/v1/talks': () => {
        attempts += 1;
        if (attempts === 1) throw new TypeError('Failed to fetch');
        return jsonResponse(page([makeTalk()]));
      },
    });
    renderAt('/');

    expect(await screen.findByRole('alert')).toHaveTextContent('Sin conexión con el servidor');
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
    expect(await screen.findByRole('link', { name: 'Accesibilidad web más allá del contraste' })).toBeInTheDocument();
    expect(callsTo(fetchMock, '/api/v1/talks')).toHaveLength(2);
  });

  it('envía los filtros a la API y los refleja en la URL', async () => {
    const fetchMock = mockApi({ 'GET /api/v1/talks': () => jsonResponse(page([])) });
    const router = renderAt('/');
    await screen.findByText('Todavía no hay charlas publicadas');

    await userEvent.type(screen.getByLabelText('Buscar'), 'laravel');
    await userEvent.click(screen.getByRole('button', { name: 'Aplicar' }));

    expect(await screen.findByText('No hay charlas con estos filtros')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?q=laravel');
    const last = callsTo(fetchMock, '/api/v1/talks').at(-1);
    expect(String(last?.[0])).toBe('/api/v1/talks?q=laravel');
  });
});
