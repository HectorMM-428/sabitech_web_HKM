import { Navigate, Outlet, useLocation } from 'react-router';
import type { UserRole } from '../api/types';
import { LoadingBlocks } from '../components/ui/StateViews';
import { useAuth } from './useAuth';

/**
 * Guardas de ruta. Son comodidad para el usuario (redirigen en vez de mostrar
 * una pantalla vacía); la seguridad real está en el backend.
 */
export function RequireAuth() {
  const { status } = useAuth();
  const location = useLocation();

  if (status === 'loading') {
    return (
      <div className="page">
        <LoadingBlocks label="Comprobando la sesión…" count={1} height={120} />
      </div>
    );
  }

  if (status === 'guest') {
    const next = encodeURIComponent(location.pathname + location.search);
    return <Navigate to={`/login?next=${next}`} replace />;
  }

  return <Outlet />;
}

export function RequireRole({ roles }: { roles: UserRole[] }) {
  const { hasRole } = useAuth();
  return hasRole(...roles) ? <Outlet /> : <Navigate to="/403" replace />;
}

