import { useEffect, useRef } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router';

/**
 * Estructura común: enlace para saltar al contenido, cabecera con navegación,
 * contenido y pie. Al cambiar de página el foco pasa al contenido principal,
 * para que teclado y lector de pantalla empiecen por la página nueva.
 */
export function AppLayout() {
  const location = useLocation();
  const mainRef = useRef<HTMLElement>(null);
  const isFirstRender = useRef(true);

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }
    mainRef.current?.focus();
  }, [location.pathname]);

  return (
    <>
      <a className="skip-link" href="#contenido">
        Saltar al contenido
      </a>

      <header className="site-header">
        <div className="site-header__inner">
          <Link to="/" className="brand" aria-label="SABITECH, ir al programa">
            <BrandMark />
            <span aria-hidden="true">SABITECH</span>
          </Link>

          <nav className="main-nav" aria-label="Principal">
            <ul>
              <li>
                <NavLink to="/" end>
                  Programa
                </NavLink>
              </li>
            </ul>
          </nav>
        </div>
      </header>

      <main id="contenido" ref={mainRef} tabIndex={-1}>
        <Outlet />
      </main>

      <footer className="site-footer">
        <div className="site-footer__inner">
          <p>Jornadas tecnológicas SABITECH</p>
          <p>Desarrollado por Sabitech</p>
        </div>
      </footer>
    </>
  );
}

function BrandMark() {
  return (
    <svg className="brand__mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
      <rect x="2" y="2" width="28" height="28" rx="8" fill="var(--color-primary)" />
      <path d="M10 20.5c1.4 1.3 3.4 2 5.6 2 3 0 4.9-1.4 4.9-3.4 0-4.6-10-2.6-10-7.4 0-1.9 1.9-3.3 4.7-3.3 1.9 0 3.6.6 4.8 1.6" fill="none" stroke="var(--color-on-primary)" strokeWidth="2.4" strokeLinecap="round" />
    </svg>
  );
}
