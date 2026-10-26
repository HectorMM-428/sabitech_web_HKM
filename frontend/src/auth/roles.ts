import type { UserRole } from '../api/types';

/** Página a la que ir tras iniciar sesión, según el rol (Fases 2–3, sección 7). */
export function homeFor(role: UserRole): string {
  if (role === 'admin') return '/admin';
  if (role === 'staff') return '/acceso';
  return '/mis-reservas';
}
