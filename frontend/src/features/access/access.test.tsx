import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { routes } from '../../routes';
import { jsonResponse, makeTalk } from '../../test/fixtures';
import { attendee, callsTo, mockApi, staffUser } from '../../test/mockApi';

function renderAt(path: string) {
  const router = createMemoryRouter(routes, { initialEntries: [path] });
  render(<RouterProvider router={router} />);
  return router;
}

const error = (status: number, code: string, message: string) => jsonResponse({ error: { code, message, fields: null } }, status);

const talk = makeTalk({ id: 7, title: 'Seguridad en APIs REST' });

function registered(overrides: Record<string, unknown> = {}) {
  return jsonResponse(
    {
      data: {
        result: 'REGISTERED',
        attendee_name: 'Lucía Ferrer',
        reservation_reference: 'SBT-7K3Q9X',
        talk_title: talk.title,
        validated_at: '2099-02-24T10:02:00+01:00',
        method: 'qr',
        ...overrides,
      },
    },
    201,
  );
}

async function pasteToken(value: string) {
  await userEvent.click(await screen.findByText(/Introduce el código/));
  await userEvent.type(screen.getByLabelText('Código leído'), value);
  await userEvent.click(screen.getByRole('button', { name: 'Validar' }));
}

const result = () => screen.getByRole('status', { name: 'Resultado de la validación' });

describe('Control de acceso (A13–A16)', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('un asistente no puede entrar en el control de acceso', async () => {
    mockApi({}, { user: attendee });
    const router = renderAt('/acceso');
    expect(await screen.findByRole('heading', { name: /No tienes permiso/ })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/403');
  });

  it('el personal ve las charlas del día con sus contadores', async () => {
    mockApi(
      {
        'GET /api/v1/staff/talks': () =>
          jsonResponse({
            data: [
              {
                id: 7,
                title: talk.title,
                room: talk.room,
                starts_at: talk.starts_at,
                ends_at: talk.ends_at,
                reservations_confirmed: 38,
                attendances: 12,
              },
            ],
          }),
      },
      { user: staffUser },
    );
    renderAt('/acceso');

    expect(await screen.findByText('38 reservas · 12 asistencias registradas')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: `Abrir control de ${talk.title}` })).toHaveAttribute('href', '/acceso/7');
  });

  it('sin HTTPS avisa de que la cámara no está disponible y ofrece alternativas', async () => {
    mockApi({ 'GET /api/v1/talks/7': () => jsonResponse({ data: talk }) }, { user: staffUser });
    renderAt('/acceso/7');

    expect(await screen.findByRole('alert')).toHaveTextContent(/conexión segura \(HTTPS\)/);
    expect(screen.getByRole('button', { name: 'Registro manual' })).toBeInTheDocument();
  });

  it('t13: un código válido muestra «Registrado» con el nombre para comprobarlo', async () => {
    const fetchMock = mockApi(
      { 'GET /api/v1/talks/7': () => jsonResponse({ data: talk }), 'POST /api/v1/checkins': () => registered() },
      { user: staffUser },
    );
    renderAt('/acceso/7');
    await pasteToken('abc.def');

    expect(await within(result()).findByText(/Registrado: Lucía Ferrer/)).toBeInTheDocument();
    const [, init] = callsTo(fetchMock, '/api/v1/checkins')[0];
    expect(JSON.parse(String(init?.body))).toEqual({ token: 'abc.def', talk_id: 7 });
  });

  it('t15: un segundo escaneo avisa de que ya estaba registrado, sin duplicar', async () => {
    mockApi(
      {
        'GET /api/v1/talks/7': () => jsonResponse({ data: talk }),
        'POST /api/v1/checkins': () =>
          jsonResponse({
            data: {
              result: 'ALREADY_REGISTERED',
              attendee_name: 'Lucía Ferrer',
              talk_title: talk.title,
              validated_at: '2099-02-24T10:02:00+01:00',
              method: 'qr',
            },
          }),
      },
      { user: staffUser },
    );
    renderAt('/acceso/7');
    await pasteToken('abc.def');

    expect(await within(result()).findByText(/Ya registrado a las 10:02/)).toBeInTheDocument();
    expect(within(result()).getByText(/No se ha creado otro registro/)).toBeInTheDocument();
  });

  it('t14: un código caducado se rechaza con el motivo del servidor', async () => {
    mockApi(
      {
        'GET /api/v1/talks/7': () => jsonResponse({ data: talk }),
        'POST /api/v1/checkins': () => error(422, 'EXPIRED', 'El código ha caducado. Pide a la persona que lo actualice.'),
      },
      { user: staffUser },
    );
    renderAt('/acceso/7');
    await pasteToken('abc.def');

    expect(await within(result()).findByText('Código rechazado')).toBeInTheDocument();
    expect(within(result()).getByText(/ha caducado/)).toBeInTheDocument();
  });

  it('un fallo de red nunca se muestra como registrado', async () => {
    mockApi(
      {
        'GET /api/v1/talks/7': () => jsonResponse({ data: talk }),
        'POST /api/v1/checkins': () => {
          throw new TypeError('Failed to fetch');
        },
      },
      { user: staffUser },
    );
    renderAt('/acceso/7');
    await pasteToken('abc.def');

    expect(await within(result()).findByText('No se pudo validar')).toBeInTheDocument();
    expect(within(result()).queryByText(/Registrado/)).not.toBeInTheDocument();
  });

  it('t16: registro manual con búsqueda y motivo obligatorio', async () => {
    const fetchMock = mockApi(
      {
        'GET /api/v1/talks/7': () => jsonResponse({ data: talk }),
        'GET /api/v1/staff/talks/7/reservations': () =>
          jsonResponse({ data: [{ reservation_id: 1842, reference: 'SBT-7K3Q9X', attendee_name: 'Lucía Ferrer', attended: false }] }),
        'POST /api/v1/checkins/manual': () => registered({ method: 'manual' }),
      },
      { user: staffUser },
    );
    renderAt('/acceso/7');

    await userEvent.click(await screen.findByRole('button', { name: 'Registro manual' }));
    await userEvent.type(screen.getByLabelText('Nombre o referencia'), 'lucía');
    await userEvent.click(screen.getByRole('button', { name: 'Buscar' }));
    await userEvent.click(await screen.findByRole('button', { name: 'Registrar a Lucía Ferrer' }));

    const submit = screen.getByRole('button', { name: 'Registrar asistencia' });
    expect(submit).toBeDisabled();
    await userEvent.type(screen.getByLabelText(/Motivo/), 'Móvil sin batería; DNI comprobado');
    await userEvent.click(submit);

    expect(await within(result()).findByText(/Registrado: Lucía Ferrer/)).toBeInTheDocument();
    expect(within(result()).getByText(/registro manual/)).toBeInTheDocument();
    expect(screen.getByText('Ya registrado')).toBeInTheDocument();
    const [, init] = callsTo(fetchMock, '/api/v1/checkins/manual')[0];
    expect(JSON.parse(String(init?.body))).toEqual({ reservation_id: 1842, reason: 'Móvil sin batería; DNI comprobado' });
  });
});
