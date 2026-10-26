import { act, render, screen } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { routes } from '../../routes';
import { jsonResponse } from '../../test/fixtures';
import { attendee, callsTo, mockApi } from '../../test/mockApi';
import { makeReservation } from '../../test/reservationFixtures';

/*
 * Con temporizadores simulados, las utilidades findBy/waitFor no avanzan solas:
 * se avanza el reloj a mano con `tick` y se comprueba con getBy/queryBy.
 */
const START = new Date('2099-02-24T09:30:00+01:00').getTime();

async function tick(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

function credentialResponse(token: string) {
  const now = Date.now();
  return jsonResponse({
    data: {
      token,
      issued_at: new Date(now).toISOString(),
      expires_at: new Date(now + 30_000).toISOString(),
      server_time: new Date(now).toISOString(),
    },
  });
}

function renderCredential() {
  const router = createMemoryRouter(routes, { initialEntries: ['/mis-reservas/1842/acreditacion'] });
  render(<RouterProvider router={router} />);
}

const qr = () => screen.queryByRole('img', { name: /Código QR de acceso/ });

describe('Acreditación QR del asistente (A12)', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(START);
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
  });

  it('t11: muestra el QR con cuenta atrás y pide uno nuevo antes de que caduque', async () => {
    let n = 0;
    const fetchMock = mockApi(
      {
        'GET /api/v1/me/reservations/1842': () => jsonResponse({ data: makeReservation() }),
        'GET /api/v1/reservations/1842/credential': () => credentialResponse(`token-${++n}`),
      },
      { user: attendee },
    );
    renderCredential();
    await tick(50);

    expect(qr()).toBeInTheDocument();
    expect(screen.getByText('Se renueva en 30 s')).toBeInTheDocument();
    expect(screen.getByText('SBT-7K3Q9X')).toBeInTheDocument();

    await tick(10_000);
    expect(screen.getByText('Se renueva en 20 s')).toBeInTheDocument();
    expect(callsTo(fetchMock, '/api/v1/reservations/1842/credential')).toHaveLength(1);

    // A los 28 s (2 s antes de caducar) se pide el siguiente.
    await tick(18_100);
    expect(callsTo(fetchMock, '/api/v1/reservations/1842/credential')).toHaveLength(2);
    expect(screen.getByText('Se renueva en 30 s')).toBeInTheDocument();
    // La región viva no anuncia las renovaciones.
    expect(screen.getByRole('status', { name: 'Estado del código' })).toBeEmptyDOMElement();
  });

  it('t12: sin conexión, el QR desaparece al caducar y vuelve cuando hay red', async () => {
    let online = true;
    let n = 0;
    mockApi(
      {
        'GET /api/v1/me/reservations/1842': () => jsonResponse({ data: makeReservation() }),
        'GET /api/v1/reservations/1842/credential': () => {
          if (!online) throw new TypeError('Failed to fetch');
          return credentialResponse(`token-${++n}`);
        },
      },
      { user: attendee },
    );
    renderCredential();
    await tick(50);
    expect(qr()).toBeInTheDocument();

    online = false;
    await tick(29_000);
    // Aún vigente: se sigue mostrando, avisando de que se intenta renovar.
    expect(qr()).toBeInTheDocument();
    expect(screen.getByText(/intentando renovar/)).toBeInTheDocument();

    await tick(2_000);
    expect(qr()).not.toBeInTheDocument();
    expect(screen.getByText('Código caducado')).toBeInTheDocument();
    expect(screen.getByText(/el código ha caducado/)).toBeInTheDocument();

    online = true;
    await tick(3_100);
    expect(qr()).toBeInTheDocument();
    expect(screen.queryByText('Código caducado')).not.toBeInTheDocument();
  });

  it('fuera del horario de acceso no hay QR y se explica cuándo estará disponible', async () => {
    const fetchMock = mockApi(
      {
        'GET /api/v1/me/reservations/1842': () => jsonResponse({ data: makeReservation() }),
        'GET /api/v1/reservations/1842/credential': () =>
          jsonResponse(
            { error: { code: 'OUTSIDE_WINDOW', message: 'El código QR estará disponible 60 minutos antes del inicio.', fields: null } },
            409,
          ),
      },
      { user: attendee },
    );
    renderCredential();
    await tick(50);

    expect(qr()).not.toBeInTheDocument();
    expect(screen.getByText(/disponible 60 minutos antes/)).toBeInTheDocument();
    // Respuesta definitiva: no se insiste.
    await tick(10_000);
    expect(callsTo(fetchMock, '/api/v1/reservations/1842/credential')).toHaveLength(1);
  });

  it('cuando el servidor dice que ya asistió, recarga la reserva y muestra la hora', async () => {
    let attended = false;
    mockApi(
      {
        'GET /api/v1/me/reservations/1842': () =>
          jsonResponse({
            data: attended
              ? makeReservation({ display_status: 'attended', attended_at: '2099-02-24T09:58:00+01:00' })
              : makeReservation(),
          }),
        'GET /api/v1/reservations/1842/credential': () => {
          if (attended) {
            return jsonResponse({ error: { code: 'ATTENDED', message: 'Tu asistencia ya está registrada.', fields: null } }, 409);
          }
          return credentialResponse('token-1');
        },
      },
      { user: attendee },
    );
    renderCredential();
    await tick(50);
    expect(qr()).toBeInTheDocument();

    attended = true;
    await tick(28_100);
    await tick(50);
    expect(qr()).not.toBeInTheDocument();
    expect(screen.getByText(/Asistencia registrada/)).toHaveTextContent('09:58');
  });

  it('una reserva cancelada no tiene acreditación', async () => {
    const fetchMock = mockApi(
      {
        'GET /api/v1/me/reservations/1842': () =>
          jsonResponse({ data: makeReservation({ status: 'cancelled', display_status: 'cancelled' }) }),
      },
      { user: attendee },
    );
    renderCredential();
    await tick(50);

    expect(screen.getByText('Esta reserva está cancelada')).toBeInTheDocument();
    expect(callsTo(fetchMock, '/api/v1/reservations/1842/credential')).toHaveLength(0);
  });
});
