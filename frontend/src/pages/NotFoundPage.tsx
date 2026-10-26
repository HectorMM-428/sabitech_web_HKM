import { Link } from 'react-router';
import { useDocumentTitle } from '../hooks/useDocumentTitle';

export function NotFoundPage() {
  useDocumentTitle('Página no encontrada');
  return (
    <div className="page">
      <h1>Página no encontrada</h1>
      <p className="muted" style={{ marginTop: 'var(--space-3)' }}>
        La dirección no existe. <Link to="/">Volver al programa</Link>
      </p>
    </div>
  );
}
