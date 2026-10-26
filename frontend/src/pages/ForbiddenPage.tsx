import { Link } from 'react-router';
import { useDocumentTitle } from '../hooks/useDocumentTitle';

export function ForbiddenPage() {
  useDocumentTitle('Sin permiso');
  return (
    <div className="page">
      <h1>No tienes permiso para ver esta página</h1>
      <p className="muted page-intro">
        Tu perfil no da acceso a esta sección. <Link to="/">Volver al programa</Link>
      </p>
    </div>
  );
}
