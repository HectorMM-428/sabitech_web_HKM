import type { NotificationStatus, Reservation } from '../../api/types';

type Tone = 'success' | 'warning' | 'danger' | 'info' | 'muted';

const RESERVATION: Record<Reservation['display_status'], { label: string; icon: string; tone: Tone }> = {
  confirmed: { label: 'Confirmada', icon: '✓', tone: 'success' },
  cancelled: { label: 'Cancelada', icon: '✕', tone: 'muted' },
  attended: { label: 'Asistida', icon: '●', tone: 'info' },
};

const EMAIL: Record<NotificationStatus, { label: string; icon: string; tone: Tone }> = {
  pending: { label: 'Correo pendiente', icon: '…', tone: 'warning' },
  sending: { label: 'Enviando correo', icon: '…', tone: 'warning' },
  sent: { label: 'Correo enviado', icon: '✉', tone: 'muted' },
  failed: { label: 'Correo no enviado', icon: '!', tone: 'danger' },
};

/** Estado de una reserva. Siempre texto + icono: el color nunca va solo (WCAG 1.4.1). */
export function ReservationBadge({ status }: { status: Reservation['display_status'] }) {
  const s = RESERVATION[status];
  return (
    <span className={`badge badge--${s.tone}`}>
      <span aria-hidden="true">{s.icon}</span> {s.label}
    </span>
  );
}

export function EmailBadge({ status }: { status: NotificationStatus }) {
  const s = EMAIL[status];
  return (
    <span className={`badge badge--${s.tone}`}>
      <span aria-hidden="true">{s.icon}</span> {s.label}
    </span>
  );
}
