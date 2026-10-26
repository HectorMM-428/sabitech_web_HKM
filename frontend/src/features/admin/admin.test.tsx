import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { routes } from '../../routes';
import { jsonResponse, makeTalk } from '../../test/fixtures';
import { adminUser, attendee, callsTo, mockApi, staffUser } from '../../test/mockApi';

function renderAt(path: string) {
  const router = createMemoryRouter(routes, { initialEntries: [path] });
  render(<RouterProvider router={router} />);
  return router;
}

const paged = <T,>(data: T[]) => ({ data, meta: { current_page: 1, per_page: 20, total: data.length, last_page: 1 } });
const error = (status: number, code: string, message: string, fields: Record<string, string[]> | null = null) =>
  jsonResponse({ error: { code, message, fields } }, status);
const bodyOf = (call: unknown[]) => JSON.parse(String((call[1] as RequestInit).body));

const speakers = { data: [{ id: 3, name: 'Marta Ibáñez', affiliation: 'Estudio Faro', bio: null, photo_url: null, photo_alt: null }] };
const rooms = { data: [{ id: 1, name: 'Aula Magna', location_details: 'Planta baja' }] };

describe('Panel de organización', () => {
  afterEach(() => vi.unstubAllGlobals());

  it.each([
    ['asistente', attendee],
    ['personal de acceso', staffUser],
  ])('t20: un %s no entra en el panel', async (_, user) => {
    mockApi({}, { user });
    const router = renderAt('/admin/charlas');
    expect(await screen.findByRole('heading', { name: /No tienes permiso/ })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/403');
  });

  it('el resumen muestra los indicadores y avisa de los correos fallidos', async () => {
    mockApi(
      {
        'GET /api/v1/admin/stats': () =>
          jsonResponse({ data: { talks: 6, reservations_confirmed: 120, attendances: 48, notifications_failed: 2, scans_rejected: 5 } }),
      },
      { user: adminUser },
    );
    renderAt('/admin');

    const failed = await screen.findByRole('link', { name: /2 Correos no enviados/ });
    expect(failed).toHaveAttribute('href', '/admin/correos?status=failed');
    expect(screen.getByRole('link', { name: /120 Reservas confirmadas/ })).toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: 'Secciones del panel' })).toBeInTheDocument();
  });

  it('crea una charla enviando la hora de Madrid en UTC y muestra los errores por campo', async () => {
    let attempt = 0;
    const fetchMock = mockApi(
      {
        'GET /api/v1/admin/speakers': () => jsonResponse(speakers),
        'GET /api/v1/admin/rooms': () => jsonResponse(rooms),
        'POST /api/v1/admin/talks': () =>
          ++attempt === 1
            ? error(422, 'VALIDATION_FAILED', 'Revisa los campos.', { room_id: ['La sala ya está ocupada a esa hora por «Charla previa».'] })
            : jsonResponse({ data: makeTalk({ status: 'draft' }) }, 201),
        'GET /api/v1/admin/talks': () => jsonResponse(paged([makeTalk({ status: 'draft', reservations_confirmed: 0, attendances: 0 })])),
      },
      { user: adminUser },
    );
    renderAt('/admin/charlas/nueva');

    await userEvent.type(await screen.findByLabelText(/^Título/), 'Seguridad en APIs REST');
    await userEvent.type(screen.getByLabelText(/^Descripción/), 'Autenticación, límites y errores bien hechos.');
    await userEvent.selectOptions(screen.getByLabelText(/^Ponente/), '3');
    await userEvent.selectOptions(screen.getByLabelText(/^Sala/), '1');
    await userEvent.type(screen.getByLabelText(/^Inicio/), '2027-02-24T10:00');
    await userEvent.type(screen.getByLabelText(/^Fin/), '2027-02-24T10:45');
    await userEvent.click(screen.getByRole('button', { name: 'Crear charla' }));

    expect(await screen.findByText('La sala ya está ocupada a esa hora por «Charla previa».')).toBeInTheDocument();
    expect(screen.getByLabelText(/^Sala/)).toHaveAttribute('aria-invalid', 'true');
    expect(bodyOf(callsTo(fetchMock, '/api/v1/admin/talks')[0])).toEqual({
      title: 'Seguridad en APIs REST',
      description: 'Autenticación, límites y errores bien hechos.',
      speaker_id: 3,
      room_id: 1,
      starts_at: '2027-02-24T09:00:00Z',
      ends_at: '2027-02-24T09:45:00Z',
      capacity: 40,
    });

    await userEvent.click(screen.getByRole('button', { name: 'Crear charla' }));
    expect(await screen.findByText('Se ha guardado «Seguridad en APIs REST».')).toBeInTheDocument();
  });

  it('t21: al editar, el aforo por debajo de las reservas muestra el motivo en el campo', async () => {
    mockApi(
      {
        'GET /api/v1/admin/speakers': () => jsonResponse(speakers),
        'GET /api/v1/admin/rooms': () => jsonResponse(rooms),
        'GET /api/v1/admin/talks/7': () => jsonResponse({ data: makeTalk({ reservations_confirmed: 28, attendances: 0 }) }),
        'PUT /api/v1/admin/talks/7': () => error(409, 'AFORO_BELOW_CONFIRMED', 'El aforo no puede ser menor que las 28 reservas confirmadas.'),
      },
      { user: adminUser },
    );
    renderAt('/admin/charlas/7');

    const capacity = await screen.findByLabelText(/^Aforo/);
    expect(capacity).toHaveValue(40);
    expect(screen.getByLabelText(/^Inicio/)).toHaveValue('2027-02-24T10:00');
    expect(screen.getByText('Mínimo 28: hay 28 reservas confirmadas.')).toBeInTheDocument();

    await userEvent.clear(capacity);
    await userEvent.type(capacity, '20');
    await userEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }));

    expect(await screen.findByText('El aforo no puede ser menor que las 28 reservas confirmadas.')).toBeInTheDocument();
    expect(capacity).toHaveAttribute('aria-invalid', 'true');
  });

  it('cancelar una charla avisa de las reservas afectadas y pide confirmación', async () => {
    let status = 'published';
    const fetchMock = mockApi(
      {
        'GET /api/v1/admin/talks': () =>
          jsonResponse(paged([makeTalk({ status: status as 'published', reservations_confirmed: 28, attendances: 0 })])),
        'PATCH /api/v1/admin/talks/7/status': () => {
          status = 'cancelled';
          return jsonResponse({ data: makeTalk({ status: 'cancelled', reservations_confirmed: 0, attendances: 0 }) });
        },
      },
      { user: adminUser },
    );
    renderAt('/admin/charlas');

    // Con reservas no se ofrece despublicar, solo cancelar.
    expect(await screen.findByRole('button', { name: /^Cancelar charla:/ })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Despublicar/ })).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /^Cancelar charla:/ }));
    const dialog = screen.getByRole('alertdialog');
    expect(within(dialog).getByText(/se cancelarán sus 28 reservas confirmadas/)).toBeInTheDocument();
    await userEvent.click(within(dialog).getByRole('button', { name: 'Cancelar charla' }));

    expect(await screen.findByText(/ahora está cancelada/)).toBeInTheDocument();
    expect(bodyOf(callsTo(fetchMock, '/api/v1/admin/talks/7/status')[0])).toEqual({ status: 'cancelled' });
    expect(screen.queryByRole('link', { name: /^Editar/ })).not.toBeInTheDocument();
  });

  it('no deja borrar un ponente con charlas y explica por qué', async () => {
    mockApi(
      {
        'GET /api/v1/admin/speakers': () => jsonResponse(speakers),
        'DELETE /api/v1/admin/speakers/3': () => error(409, 'HAS_TALKS', 'Este ponente tiene charlas asociadas: no se puede borrar.'),
      },
      { user: adminUser },
    );
    renderAt('/admin/ponentes');

    await userEvent.click(await screen.findByRole('button', { name: 'Borrar Marta Ibáñez' }));
    await userEvent.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Borrar' }));

    expect(await screen.findByText('Este ponente tiene charlas asociadas: no se puede borrar.')).toBeInTheDocument();
    expect(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Borrar' })).toBeDisabled();
  });

  it('da de alta una sala y vuelve a pedir la lista', async () => {
    const fetchMock = mockApi(
      {
        'GET /api/v1/admin/rooms': () => jsonResponse(rooms),
        'POST /api/v1/admin/rooms': () => jsonResponse({ data: { id: 2, name: 'Laboratorio 2', location_details: null } }, 201),
      },
      { user: adminUser },
    );
    renderAt('/admin/salas');

    await userEvent.click(await screen.findByRole('button', { name: 'Añadir sala' }));
    await userEvent.type(screen.getByLabelText(/^Nombre/), 'Laboratorio 2');
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }));

    expect(await screen.findByText('Se ha guardado «Laboratorio 2».')).toBeInTheDocument();
    expect(bodyOf(callsTo(fetchMock, '/api/v1/admin/rooms').find(([, init]) => init?.method === 'POST')!)).toEqual({
      name: 'Laboratorio 2',
      location_details: null,
    });
    expect(callsTo(fetchMock, '/api/v1/admin/rooms').filter(([, init]) => !init?.method || init.method === 'GET')).toHaveLength(2);
  });

  it('t10: reintenta un correo fallido sin tocar la reserva', async () => {
    const fetchMock = mockApi(
      {
        'GET /api/v1/admin/notifications': () =>
          jsonResponse(
            paged([
              {
                id: 9,
                reservation_reference: 'SBT-7K3Q9X',
                recipient: 'lucia@example.test',
                status: 'failed',
                attempts: 5,
                last_error: 'Connection refused',
                last_attempt_at: '2027-02-10T18:40:00+01:00',
                sent_at: null,
              },
            ]),
          ),
        'POST /api/v1/admin/notifications/9/retry': () =>
          jsonResponse({ data: { id: 9, reservation_reference: 'SBT-7K3Q9X', recipient: 'lucia@example.test', status: 'pending', attempts: 5 } }, 202),
      },
      { user: adminUser },
    );
    renderAt('/admin/correos?status=failed');

    expect(await screen.findByText('Connection refused')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar el correo de SBT-7K3Q9X' }));

    expect(await screen.findByText(/Reintento encolado para lucia@example.test/)).toBeInTheDocument();
    expect(callsTo(fetchMock, '/api/v1/admin/notifications/9/retry')).toHaveLength(1);
    expect(String(callsTo(fetchMock, '/api/v1/admin/notifications')[0][0])).toContain('status=failed');
  });

  it('los filtros de reservas viajan en la URL y en la petición', async () => {
    const fetchMock = mockApi(
      {
        'GET /api/v1/admin/reservations': () =>
          jsonResponse(
            paged([
              {
                id: 1842,
                reference: 'SBT-7K3Q9X',
                attendee: { id: 21, name: 'Lucía Ferrer', email: 'lucia@example.test' },
                talk_id: 7,
                talk_title: 'Seguridad en APIs REST',
                display_status: 'attended',
                email_status: 'sent',
                confirmed_at: '2027-02-10T18:22:04+01:00',
                cancelled_at: null,
              },
            ]),
          ),
      },
      { user: adminUser },
    );
    const router = renderAt('/admin/reservas');

    expect(await screen.findByText('Lucía Ferrer')).toBeInTheDocument();
    expect(within(screen.getByRole('table')).getByText('Asistida')).toBeInTheDocument();

    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'attended');
    await userEvent.type(screen.getByLabelText('Buscar'), 'lucía');
    await userEvent.click(screen.getByRole('button', { name: 'Buscar' }));
    await userEvent.click(await screen.findByRole('button', { name: 'Seguridad en APIs REST' }));

    expect(router.state.location.search).toBe('?status=attended&q=luc%C3%ADa&talk_id=7');
    const last = new URL(String(callsTo(fetchMock, '/api/v1/admin/reservations').at(-1)![0]), 'http://localhost');
    expect(Object.fromEntries(last.searchParams)).toEqual({ status: 'attended', q: 'lucía', talk_id: '7', page: '1' });
  });

  it('cambia el rol de otro usuario; el propio no se puede cambiar', async () => {
    const fetchMock = mockApi(
      {
        'GET /api/v1/admin/users': () =>
          jsonResponse(paged([adminUser, { id: 21, name: 'Lucía Ferrer', email: 'lucia@example.test', role: 'attendee' }])),
        'PATCH /api/v1/admin/users/21/role': () =>
          jsonResponse({ data: { id: 21, name: 'Lucía Ferrer', email: 'lucia@example.test', role: 'staff' } }),
      },
      { user: adminUser },
    );
    renderAt('/admin/usuarios');

    expect(await screen.findByText(`${adminUser.name} (tú)`)).toBeInTheDocument();
    expect(screen.queryByLabelText(`Rol de ${adminUser.name}`)).not.toBeInTheDocument();

    const save = screen.getByRole('button', { name: 'Guardar rol' });
    expect(save).toBeDisabled();
    await userEvent.selectOptions(screen.getByLabelText('Rol de Lucía Ferrer'), 'staff');
    await userEvent.click(save);

    expect(await screen.findByText('Lucía Ferrer ahora tiene el rol «Personal de acceso».')).toBeInTheDocument();
    expect(bodyOf(callsTo(fetchMock, '/api/v1/admin/users/21/role')[0])).toEqual({ role: 'staff' });
  });
});
