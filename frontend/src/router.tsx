import { createBrowserRouter, Navigate } from 'react-router';
import { AppShell } from './components/layout/AppShell';
import { FleetMapPage } from './pages/admin/FleetMapPage';
import { ExceptionMapPage } from './pages/admin/ExceptionMapPage';
import { RouteAnalysisPage } from './pages/admin/RouteAnalysisPage';
import { RouteDetailPage } from './pages/admin/RouteDetailPage';
import { CustomerRouteDetailPage } from './pages/customer/CustomerRouteDetailPage';
import { DriverRoutePage } from './pages/driver/DriverRoutePage';

export const router = createBrowserRouter([
  {
    path: '/app',
    children: [
      // Fleet map is full-screen with its own sidebar — no AppShell
      { path: 'admin/fleet-map', element: <FleetMapPage /> },
      { path: 'admin/routes/:publicId', element: <RouteDetailPage /> },
      // Customer route detail — full-screen with sidebar + map
      { path: 'customer/routes/:publicId', element: <CustomerRouteDetailPage /> },
      // Driver route — full-screen with sidebar + map, delivery execution focus
      { path: 'driver/routes/:publicId', element: <DriverRoutePage /> },
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
