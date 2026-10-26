import type { TalkStatus, UserRole } from '../../api/types';

export const TALK_STATUS: Record<TalkStatus, { label: string; icon: string; tone: 'success' | 'warning' | 'muted' }> = {
  draft: { label: 'Borrador', icon: '✎', tone: 'warning' },
  published: { label: 'Publicada', icon: '✓', tone: 'success' },
  cancelled: { label: 'Cancelada', icon: '✕', tone: 'muted' },
};

export const ROLE_LABEL: Record<UserRole, string> = {
  attendee: 'Asistente',
  staff: 'Personal de acceso',
  admin: 'Administración',
};

export const METHOD_LABEL = { qr: 'QR', manual: 'Manual' } as const;
