import type { RouteObject } from 'react-router';
import { AppLayout } from './components/layout/AppLayout';
import { CatalogPage } from './features/catalog/CatalogPage';
import { TalkDetailPage } from './features/catalog/TalkDetailPage';
import { NotFoundPage } from './pages/NotFoundPage';

/**
 * Rutas de la aplicación (mapa de pantallas de la Fase 1, sección I).
 * Las rutas privadas (mis reservas, acceso, panel) se añaden en sus hitos con sus guardas.
 */
export const routes: RouteObject[] = [
  {
    element: <AppLayout />,
    children: [
      { index: true, element: <CatalogPage /> },
      { path: 'charlas/:talkId', element: <TalkDetailPage /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
];
