import { createBrowserRouter, Navigate } from 'react-router';
import { AppShell } from './components/layout/AppShell';
import { FleetMapPage } from './pages/admin/FleetMapPage';

export const router = createBrowserRouter([
  {
    path: '/app',
    children: [
      // Fleet map is full-screen with its own sidebar — no AppShell
      { path: 'admin/fleet-map', element: <FleetMapPage /> },
      {
        element: <AppShell />,
        children: [
          // Future pages that use the standard AppShell layout go here
        ],
      },
      { index: true, element: <Navigate to="admin/fleet-map" replace /> },
    ],
  },
]);
