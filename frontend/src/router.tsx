import { createBrowserRouter, Navigate } from 'react-router';
import { FleetMapPage } from './pages/admin/FleetMapPage';
import { ExceptionMapPage } from './pages/admin/ExceptionMapPage';
import { RouteAnalysisPage } from './pages/admin/RouteAnalysisPage';
import { RouteDetailPage } from './pages/admin/RouteDetailPage';
import { TestRoutingPage } from './pages/admin/TestRoutingPage';
import { OperatorDashboardPage } from './pages/admin/OperatorDashboardPage';
import { RoutePlannerPage } from './pages/admin/RoutePlannerPage';
import { CustomerRouteDetailPage } from './pages/customer/CustomerRouteDetailPage';
import { DriverRoutePage } from './pages/driver/DriverRoutePage';

export const router = createBrowserRouter([
  {
    path: '/app',
    children: [
      // Fleet map is full-screen with its own sidebar — no AppShell
      { path: 'admin/fleet-map', element: <FleetMapPage /> },
      // Exception heatmap — full-screen with sidebar
      { path: 'admin/exception-map', element: <ExceptionMapPage /> },
      { path: 'admin/routes/:publicId', element: <RouteDetailPage /> },
      { path: 'admin/test-routing', element: <TestRoutingPage /> },
      { path: 'admin/operator-dashboard', element: <OperatorDashboardPage /> },
      { path: 'admin/route-planner', element: <RoutePlannerPage /> },
      // Route analysis — full-screen with sidebar
      { path: 'admin/routes/:publicId/analysis', element: <RouteAnalysisPage /> },
      // Customer route detail — full-screen with sidebar + map
      { path: 'customer/routes/:publicId', element: <CustomerRouteDetailPage /> },
      // Driver route — full-screen with sidebar + map, delivery execution focus
      { path: 'driver/routes/:publicId', element: <DriverRoutePage /> },
{ index: true, element: <Navigate to="admin/fleet-map" replace /> },
    ],
  },
]);
