import { Link } from 'react-router';
import { getStats } from '../../api/admin';
import { ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';

/** A28 · Resumen del panel: números calculados en el momento por el servidor. */
export function DashboardPage() {
  useDocumentTitle('Panel de organización');
  const { state, reload } = useApi((signal) => getStats(signal), 'stats');

  return (
    <>
      <h1>Panel de organización</h1>
      {state.status === 'loading' && <LoadingBlocks label="Cargando indicadores…" count={5} height={96} />}
      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}
      {state.status === 'success' && (
        <ul className="stat-grid">
          <Stat value={state.data.data.talks} label="Charlas publicadas" to="/admin/charlas?status=published" />
          <Stat value={state.data.data.reservations_confirmed} label="Reservas confirmadas" to="/admin/reservas?status=confirmed" />
          <Stat value={state.data.data.attendances} label="Asistencias registradas" to="/admin/asistencias" />
          <Stat
            value={state.data.data.notifications_failed}
            label="Correos no enviados"
            to="/admin/correos?status=failed"
            alert={state.data.data.notifications_failed > 0}
          />
          <Stat value={state.data.data.scans_rejected} label="Lecturas de QR rechazadas" />
        </ul>
      )}
    </>
  );
}

function Stat({ value, label, to, alert = false }: { value: number; label: string; to?: string; alert?: boolean }) {
  const body = (
    <>
      <span className="stat__value">{value}</span>{' '}
      <span className="stat__label">
        {alert ? <span aria-hidden="true">! </span> : null}
        {label}
      </span>
    </>
  );
  return (
    <li className={`stat${alert ? ' stat--alert' : ''}`}>
      {to ? (
        <Link to={to} className="stat__link">
          {body}
        </Link>
      ) : (
        body
      )}
    </li>
  );
}
