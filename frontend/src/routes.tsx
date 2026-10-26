import type { RouteObject } from 'react-router';
import { AuthProvider } from './auth/AuthContext';
import { RequireAuth, RequireRole } from './auth/guards';
import { AppLayout } from './components/layout/AppLayout';
import { LoginPage } from './features/auth/LoginPage';
import { RegisterPage } from './features/auth/RegisterPage';
import { CatalogPage } from './features/catalog/CatalogPage';
import { TalkDetailPage } from './features/catalog/TalkDetailPage';
import { MyReservationsPage } from './features/reservations/MyReservationsPage';
import { ForbiddenPage } from './pages/ForbiddenPage';
import { NotFoundPage } from './pages/NotFoundPage';

/**
 * Rutas de la aplicación (mapa de pantallas de la Fase 1, sección I).
 * Las privadas pasan por RequireAuth y RequireRole (src/auth/guards.tsx).
 */
export const routes: RouteObject[] = [
  {
    element: (
      <AuthProvider>
        <AppLayout />
      </AuthProvider>
    ),
    children: [
      { index: true, element: <CatalogPage /> },
      { path: 'charlas/:talkId', element: <TalkDetailPage /> },
      { path: 'login', element: <LoginPage /> },
      { path: 'registro', element: <RegisterPage /> },
      { path: '403', element: <ForbiddenPage /> },
      {
        element: <RequireAuth />,
        children: [
          {
            element: <RequireRole roles={['attendee']} />,
            children: [{ path: 'mis-reservas', element: <MyReservationsPage /> }],
          },
        ],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
];
