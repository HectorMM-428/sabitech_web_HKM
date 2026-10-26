import type { RouteObject } from 'react-router';
import { AuthProvider } from './auth/AuthContext';
import { RequireAuth, RequireRole } from './auth/guards';
import { AppLayout } from './components/layout/AppLayout';
import { LoginPage } from './features/auth/LoginPage';
import { RegisterPage } from './features/auth/RegisterPage';
import { CatalogPage } from './features/catalog/CatalogPage';
import { TalkDetailPage } from './features/catalog/TalkDetailPage';
import { AccessHomePage } from './features/access/AccessHomePage';
import { AdminLayout } from './features/admin/AdminLayout';
import { AttendancesPage } from './features/admin/AttendancesPage';
import { DashboardPage } from './features/admin/DashboardPage';
import { NotificationsPage } from './features/admin/NotificationsPage';
import { ReservationsAdminPage } from './features/admin/ReservationsAdminPage';
import { RoomsPage } from './features/admin/RoomsPage';
import { SpeakersPage } from './features/admin/SpeakersPage';
import { TalkFormPage } from './features/admin/TalkFormPage';
import { TalksAdminPage } from './features/admin/TalksAdminPage';
import { UsersPage } from './features/admin/UsersPage';
import { ScannerPage } from './features/access/ScannerPage';
import { CredentialPage } from './features/credential/CredentialPage';
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
            children: [
              { path: 'mis-reservas', element: <MyReservationsPage /> },
              { path: 'mis-reservas/:reservationId/acreditacion', element: <CredentialPage /> },
            ],
          },
          {
            element: <RequireRole roles={['staff', 'admin']} />,
            children: [
              { path: 'acceso', element: <AccessHomePage /> },
              { path: 'acceso/:talkId', element: <ScannerPage /> },
            ],
          },
          {
            element: <RequireRole roles={['admin']} />,
            children: [
              {
                path: 'admin',
                element: <AdminLayout />,
                children: [
                  { index: true, element: <DashboardPage /> },
                  { path: 'charlas', element: <TalksAdminPage /> },
                  { path: 'charlas/nueva', element: <TalkFormPage /> },
                  { path: 'charlas/:talkId', element: <TalkFormPage key="edit" /> },
                  { path: 'ponentes', element: <SpeakersPage /> },
                  { path: 'salas', element: <RoomsPage /> },
                  { path: 'reservas', element: <ReservationsAdminPage /> },
                  { path: 'asistencias', element: <AttendancesPage /> },
                  { path: 'correos', element: <NotificationsPage /> },
                  { path: 'usuarios', element: <UsersPage /> },
                ],
              },
            ],
          },
        ],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
];
