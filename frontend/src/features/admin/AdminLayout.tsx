import { NavLink, Outlet } from 'react-router';

const SECTIONS = [
  { to: '/admin', label: 'Resumen', end: true },
  { to: '/admin/charlas', label: 'Charlas' },
  { to: '/admin/ponentes', label: 'Ponentes' },
  { to: '/admin/salas', label: 'Salas' },
  { to: '/admin/reservas', label: 'Reservas' },
  { to: '/admin/asistencias', label: 'Asistencias' },
  { to: '/admin/correos', label: 'Correos' },
  { to: '/admin/usuarios', label: 'Usuarios' },
];

/** Panel de organización: menú de secciones y la sección activa. */
export function AdminLayout() {
  return (
    <div className="page admin">
      <nav className="admin-nav" aria-label="Secciones del panel">
        <ul>
          {SECTIONS.map((s) => (
            <li key={s.to}>
              <NavLink to={s.to} end={s.end}>
                {s.label}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>
      <div className="admin__content">
        <Outlet />
      </div>
    </div>
  );
}
