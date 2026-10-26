import { render, screen } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { routes } from '../../routes';
import { jsonResponse, makeTalk } from '../../test/fixtures';

function renderAt(path: string) {
  render(<RouterProvider router={createMemoryRouter(routes, { initialEntries: [path] })} />);
}

describe('Detalle de charla (A02)', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('muestra los datos obligatorios de la charla', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: makeTalk({ available_seats: 3 }) })));
    renderAt('/charlas/7');

    expect(await screen.findByRole('heading', { level: 1, name: 'Accesibilidad web más allá del contraste' })).toBeInTheDocument();
    expect(screen.getByText('miércoles, 24 de febrero')).toBeInTheDocument();
    expect(screen.getByText('10:00–10:45')).toBeInTheDocument();
    expect(screen.getByText('Aula Magna')).toBeInTheDocument();
    expect(screen.getByText('Marta Ibáñez')).toBeInTheDocument();
    expect(screen.getByText(/Últimas 3 plazas de 40/)).toBeInTheDocument();
    expect(document.title).toBe('Accesibilidad web más allá del contraste · SABITECH');
  });

  it('una charla inexistente muestra un mensaje claro (404)', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(jsonResponse({ error: { code: 'NOT_FOUND', message: 'No se ha encontrado el recurso.', fields: null } }, 404)),
    );
    renderAt('/charlas/999');
    expect(await screen.findByText('Esta charla no existe o no está publicada')).toBeInTheDocument();
  });

  it('un identificador no numérico no llama a la API', async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    renderAt('/charlas/abc');
    expect(await screen.findByText('Esta charla no existe o no está publicada')).toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
