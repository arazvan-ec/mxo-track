import { createBrowserRouter, Navigate } from 'react-router';
import { AppShell } from './components/layout/AppShell';
import { FleetMapPage } from './pages/admin/FleetMapPage';

export const router = createBrowserRouter([
  {
    path: '/app',
    element: <AppShell />,
    children: [
      { path: 'admin/fleet-map', element: <FleetMapPage /> },
      { index: true, element: <Navigate to="admin/fleet-map" replace /> },
    ],
  },
]);
