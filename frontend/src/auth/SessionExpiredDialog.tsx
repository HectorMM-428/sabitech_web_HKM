import { useLocation, useNavigate } from 'react-router';
import { Button } from '../components/ui/Form';
import { Modal } from '../components/ui/Modal';
import { useAuth } from './useAuth';

/**
 * Aviso de sesión caducada (§4.7 del enunciado). Al volver a entrar se regresa a la
 * misma página; la acción que falló NO se repite sola: el usuario la confirma de nuevo.
 */
export function SessionExpiredDialog() {
  const { sessionExpired, dismissSessionExpired } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  if (!sessionExpired) return null;

  const next = encodeURIComponent(location.pathname + location.search);

  return (
    <Modal
      role="alertdialog"
      title="Tu sesión ha caducado"
      onClose={dismissSessionExpired}
      actions={
        <>
          <Button
            onClick={() => {
              dismissSessionExpired();
              navigate(`/login?next=${next}`);
            }}
          >
            Volver a entrar
          </Button>
          <Button variant="secondary" onClick={dismissSessionExpired}>
            Cerrar
          </Button>
        </>
      }
    >
      <p>Por seguridad, vuelve a iniciar sesión. Lo que estabas haciendo no se ha completado.</p>
    </Modal>
  );
}
