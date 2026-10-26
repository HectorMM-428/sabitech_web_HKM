import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { routes } from '../../routes';
import { jsonResponse, makeTalk } from '../../test/fixtures';
import { attendee, callsTo, mockApi, staffUser } from '../../test/mockApi';
import { makeReservation } from '../../test/reservationFixtures';

function renderAt(path: string) {
  const router = createMemoryRouter(routes, { initialEntries: [path] });
  render(<RouterProvider router={router} />);
  return router;
}

const error = (status: number, code: string, message: string) => jsonResponse({ error: { code, message, fields: null } }, status);

describe('Reservar desde el detalle de la charla (A10)', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('un visitante ve cómo entrar para reservar, con vuelta a la charla', async () => {
    mockApi({ 'GET /api/v1/talks/7': () => jsonResponse({ data: makeTalk() }) });
    renderAt('/charlas/7');

    const link = await screen.findByRole('link', { name: 'Entrar para reservar' });
    expect(link).toHaveAttribute('href', '/login?next=%2Fcharlas%2F7');
    expect(screen.queryByRole('button', { name: 'Reservar plaza' })).not.toBeInTheDocument();
  });

  it('t05: el asistente reserva, ve su referencia y las plazas se vuelven a pedir al servidor', async () => {
    let seats = 12;
    const fetchMock = mockApi(
      {
        'GET /api/v1/talks/7': () => jsonResponse({ data: makeTalk({ available_seats: seats }) }),
        'GET /api/v1/me/reservations?status=confirmed': () => jsonResponse({ data: [] }),
        'POST /api/v1/reservations': () => {
          seats -= 1;
          return jsonResponse({ data: makeReservation({ reference: 'SBT-ABC234' }) }, 201);
        },
      },
      { user: attendee },
    );
    renderAt('/charlas/7');

    await userEvent.click(await screen.findByRole('button', { name: 'Reservar plaza' }));

    expect(await screen.findByText(/Reserva confirmada/)).toBeInTheDocument();
    expect(screen.getByText('SBT-ABC234')).toBeInTheDocument();
    expect(await screen.findByText(/11 de 40 plazas libres/)).toBeInTheDocument();
    expect(callsTo(fetchMock, '/api/v1/talks/7')).toHaveLength(2);
    const post = callsTo(fetchMock, '/api/v1/reservations').find(([, init]) => init?.method === 'POST');
    expect(post?.[1]?.body).toBe(JSON.stringify({ talk_id: 7 }));
  });

  it('t07: si otra persona se llevó la última plaza, muestra el mensaje del servidor y actualiza', async () => {
    let seats = 1;
    mockApi(
      {
        'GET /api/v1/talks/7': () => jsonResponse({ data: makeTalk({ available_seats: seats }) }),
        'GET /api/v1/me/reservations?status=confirmed': () => jsonResponse({ data: [] }),
        'POST /api/v1/reservations': () => {
          seats = 0;
          return error(409, 'TALK_FULL', 'La charla no tiene plazas disponibles.');
        },
      },
      { user: attendee },
    );
    renderAt('/charlas/7');

    await userEvent.click(await screen.findByRole('button', { name: 'Reservar plaza' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('La charla no tiene plazas disponibles.');
    expect(await screen.findByText(/Esta charla está completa/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Reservar plaza' })).not.toBeInTheDocument();
  });

  it('si ya tiene plaza, lo indica en vez de ofrecer reservar otra vez', async () => {
    mockApi(
      {
        'GET /api/v1/talks/7': () => jsonResponse({ data: makeTalk() }),
        'GET /api/v1/me/reservations?status=confirmed': () => jsonResponse({ data: [makeReservation({ talk: makeTalk() })] }),
      },
      { user: attendee },
    );
    renderAt('/charlas/7');

    expect(await screen.findByText(/Ya tienes plaza en esta charla/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Reservar plaza' })).not.toBeInTheDocument();
  });

  it('el personal de acceso no ve el botón de reservar', async () => {
    mockApi({ 'GET /api/v1/talks/7': () => jsonResponse({ data: makeTalk() }) }, { user: staffUser });
    renderAt('/charlas/7');
    expect(await screen.findByText(/Las reservas son para asistentes/)).toBeInTheDocument();
  });
});

describe('Mis reservas y cancelación (A08, A11)', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('un visitante es enviado a iniciar sesión', async () => {
    mockApi({});
    const router = renderAt('/mis-reservas');
    await vi.waitFor(() => expect(router.state.location.pathname).toBe('/login'));
    expect(router.state.location.search).toBe('?next=%2Fmis-reservas');
  });

  it('el personal de acceso no puede entrar (403)', async () => {
    mockApi({}, { user: staffUser });
    const router = renderAt('/mis-reservas');
    await vi.waitFor(() => expect(router.state.location.pathname).toBe('/403'));
  });

  it('muestra cada reserva con su estado y el del correo, sin depender solo del color', async () => {
    mockApi(
      {
        'GET /api/v1/me/reservations': () =>
          jsonResponse({
            data: [
              makeReservation({ id: 1, email_status: 'failed' }),
              makeReservation({ id: 2, reference: 'SBT-CANCEL', status: 'cancelled', display_status: 'cancelled', cancelled_at: '2027-02-11T10:00:00+01:00' }),
            ],
          }),
      },
      { user: attendee },
    );
    renderAt('/mis-reservas');

    const upcoming = await screen.findByRole('region', { name: 'Próximas' });
    expect(within(upcoming).getByText('Confirmada')).toBeInTheDocument();
    expect(within(upcoming).getByText('Correo no enviado')).toBeInTheDocument();
    expect(within(upcoming).getByText(/La organización reenviará el correo/)).toBeInTheDocument();
    const history = screen.getByRole('region', { name: 'Historial' });
    expect(within(history).getByText('Cancelada')).toBeInTheDocument();
    expect(within(history).queryByRole('button', { name: 'Cancelar reserva' })).not.toBeInTheDocument();
  });

  it('t09: cancelar pide confirmación, llama a la API y recarga la lista', async () => {
    let cancelled = false;
    const fetchMock = mockApi(
      {
        'GET /api/v1/me/reservations': () =>
          jsonResponse({
            data: [cancelled ? makeReservation({ status: 'cancelled', display_status: 'cancelled' }) : makeReservation()],
          }),
        'POST /api/v1/reservations/1842/cancel': () => {
          cancelled = true;
          return jsonResponse({ data: makeReservation({ status: 'cancelled', display_status: 'cancelled' }) });
        },
      },
      { user: attendee },
    );
    renderAt('/mis-reservas');

    await userEvent.click(await screen.findByRole('button', { name: 'Cancelar reserva' }));
    const dialog = screen.getByRole('dialog', { name: '¿Cancelar la reserva?' });
    expect(within(dialog).getByRole('button', { name: 'Sí, cancelar' })).toHaveFocus();

    await userEvent.click(within(dialog).getByRole('button', { name: 'Sí, cancelar' }));

    expect(await screen.findByText('Reserva SBT-7K3Q9X cancelada. La plaza ha quedado libre.')).toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(await screen.findByText('Cancelada')).toBeInTheDocument();
    expect(callsTo(fetchMock, '/api/v1/reservations/1842/cancel')).toHaveLength(1);
  });

  it('Escape cierra el diálogo sin cancelar', async () => {
    const fetchMock = mockApi({ 'GET /api/v1/me/reservations': () => jsonResponse({ data: [makeReservation()] }) }, { user: attendee });
    renderAt('/mis-reservas');

    const trigger = await screen.findByRole('button', { name: 'Cancelar reserva' });
    await userEvent.click(trigger);
    await userEvent.keyboard('{Escape}');

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
    expect(callsTo(fetchMock, '/api/v1/reservations/1842/cancel')).toHaveLength(0);
  });

  it('si la sesión caduca al cancelar, avisa y no da la operación por hecha', async () => {
    mockApi(
      {
        'GET /api/v1/me/reservations': () => jsonResponse({ data: [makeReservation()] }),
        'POST /api/v1/reservations/1842/cancel': () => error(401, 'UNAUTHENTICATED', 'Tu sesión ha caducado. Vuelve a iniciar sesión.'),
      },
      { user: attendee },
    );
    renderAt('/mis-reservas');

    await userEvent.click(await screen.findByRole('button', { name: 'Cancelar reserva' }));
    await userEvent.click(screen.getByRole('button', { name: 'Sí, cancelar' }));

    expect(await screen.findByRole('alertdialog', { name: 'Tu sesión ha caducado' })).toBeInTheDocument();
    expect(screen.queryByText(/ha quedado libre/)).not.toBeInTheDocument();
  });
});
