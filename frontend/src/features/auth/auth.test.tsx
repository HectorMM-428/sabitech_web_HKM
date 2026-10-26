import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { safeNext } from '../../auth/redirect';
import { routes } from '../../routes';
import { jsonResponse, page } from '../../test/fixtures';
import { adminUser, attendee, callsTo, mockApi } from '../../test/mockApi';

function renderAt(path: string) {
  const router = createMemoryRouter(routes, { initialEntries: [path] });
  render(<RouterProvider router={router} />);
  return router;
}

describe('Inicio de sesión (A04)', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('pide la cookie CSRF, inicia sesión y vuelve a la página de origen', async () => {
    const fetchMock = mockApi({
      'POST /api/v1/auth/login': () => jsonResponse({ data: attendee }),
      'GET /api/v1/talks': () => jsonResponse(page([])),
    });
    const router = renderAt('/login?next=%2F');

    await userEvent.type(await screen.findByLabelText(/Email/), 'lucia@sabitech.test');
    await userEvent.type(screen.getByLabelText(/Contraseña/), 'Clave-segura-2026');
    await userEvent.click(screen.getByRole('button', { name: 'Entrar' }));

    expect(await screen.findByRole('heading', { name: 'Programa de charlas' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/');
    expect(callsTo(fetchMock, '/sanctum/csrf-cookie')).toHaveLength(1);
    expect(screen.getByRole('button', { name: 'Salir' })).toBeInTheDocument();
    expect(screen.getByText('Lucía Ferrer')).toBeInTheDocument();
  });

  it('el administrador va a su panel tras entrar', async () => {
    mockApi({
      'POST /api/v1/auth/login': () => jsonResponse({ data: adminUser }),
      'GET /api/v1/admin/stats': () =>
        jsonResponse({ data: { talks: 0, reservations_confirmed: 0, attendances: 0, notifications_failed: 0, scans_rejected: 0 } }),
    });
    const router = renderAt('/login');

    await userEvent.type(await screen.findByLabelText(/Email/), 'admin@sabitech.test');
    await userEvent.type(screen.getByLabelText(/Contraseña/), 'x');
    await userEvent.click(screen.getByRole('button', { name: 'Entrar' }));

    await vi.waitFor(() => expect(router.state.location.pathname).toBe('/admin'));
  });

  it('muestra el error del servidor junto al campo y lo asocia por aria-describedby', async () => {
    mockApi({
      'POST /api/v1/auth/login': () =>
        jsonResponse(
          {
            error: {
              code: 'VALIDATION_FAILED',
              message: 'Revisa los campos marcados.',
              fields: { email: ['El email o la contraseña no son correctos.'] },
            },
          },
          422,
        ),
    });
    renderAt('/login');

    const email = await screen.findByLabelText(/Email/);
    await userEvent.type(email, 'lucia@sabitech.test');
    await userEvent.type(screen.getByLabelText(/Contraseña/), 'mala');
    await userEvent.click(screen.getByRole('button', { name: 'Entrar' }));

    const message = await screen.findByText('El email o la contraseña no son correctos.');
    expect(email).toHaveAttribute('aria-invalid', 'true');
    expect(email.getAttribute('aria-describedby')).toContain(message.closest('p')!.id);
  });

  it('demasiados intentos: muestra el aviso general del servidor', async () => {
    mockApi({
      'POST /api/v1/auth/login': () =>
        jsonResponse({ error: { code: 'TOO_MANY_REQUESTS', message: 'Demasiados intentos. Espera un minuto.', fields: null } }, 429),
    });
    renderAt('/login');
    await userEvent.type(await screen.findByLabelText(/Email/), 'lucia@sabitech.test');
    await userEvent.type(screen.getByLabelText(/Contraseña/), 'x');
    await userEvent.click(screen.getByRole('button', { name: 'Entrar' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Demasiados intentos');
  });
});

describe('Registro (A05)', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('comprueba en el navegador que las contraseñas coinciden sin llamar a la API', async () => {
    const fetchMock = mockApi({});
    renderAt('/registro');

    await userEvent.type(await screen.findByLabelText(/Nombre y apellidos/), 'Irene Navarro');
    await userEvent.type(screen.getByLabelText(/^Email/), 'irene@sabitech.test');
    await userEvent.type(screen.getByLabelText(/^Contraseña/), 'Clave-segura-2026');
    await userEvent.type(screen.getByLabelText(/Repite la contraseña/), 'Otra-clave-2026');
    await userEvent.click(screen.getByRole('button', { name: 'Crear cuenta' }));

    expect(await screen.findByText('Las contraseñas no coinciden.')).toBeInTheDocument();
    expect(callsTo(fetchMock, '/api/v1/auth/register')).toHaveLength(0);
  });

  it('muestra los errores de validación del servidor en cada campo', async () => {
    mockApi({
      'POST /api/v1/auth/register': () =>
        jsonResponse(
          {
            error: {
              code: 'VALIDATION_FAILED',
              message: 'Revisa los campos marcados.',
              fields: { email: ['Ya existe una cuenta con este email. Inicia sesión.'] },
            },
          },
          422,
        ),
    });
    renderAt('/registro');

    await userEvent.type(await screen.findByLabelText(/Nombre y apellidos/), 'Lucía');
    await userEvent.type(screen.getByLabelText(/^Email/), 'lucia@sabitech.test');
    await userEvent.type(screen.getByLabelText(/^Contraseña/), 'Clave-segura-2026');
    await userEvent.type(screen.getByLabelText(/Repite la contraseña/), 'Clave-segura-2026');
    await userEvent.click(screen.getByRole('button', { name: 'Crear cuenta' }));

    expect(await screen.findByText('Ya existe una cuenta con este email. Inicia sesión.')).toBeInTheDocument();
    expect(screen.getByLabelText(/^Email/)).toHaveAttribute('aria-invalid', 'true');
  });
});

describe('Redirección segura tras el login', () => {
  it('solo acepta rutas internas', () => {
    expect(safeNext('/mis-reservas')).toBe('/mis-reservas');
    expect(safeNext('https://malicioso.example')).toBeNull();
    expect(safeNext('//malicioso.example')).toBeNull();
    expect(safeNext('/\\malicioso.example')).toBeNull();
    expect(safeNext(null)).toBeNull();
  });
});
