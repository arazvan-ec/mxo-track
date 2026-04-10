import { createBrowserRouter, Navigate } from 'react-router';
import { AppLayout } from './components/layout/AppLayout';
import { FleetMapPage } from './pages/admin/FleetMapPage';
import { ExceptionMapPage } from './pages/admin/ExceptionMapPage';
import { RouteAnalysisPage } from './pages/admin/RouteAnalysisPage';
import { RouteDetailPage } from './pages/admin/RouteDetailPage';
import { TestRoutingPage } from './pages/admin/TestRoutingPage';
import { OperatorDashboardPage } from './pages/admin/OperatorDashboardPage';
import { RoutePlannerPage } from './pages/admin/RoutePlannerPage';
import { WidgetGalleryPage } from './pages/admin/WidgetGalleryPage';
import { PageLayoutEditorPage } from './pages/admin/PageLayoutEditorPage';
import { AdminDashboardPage } from './pages/admin/AdminDashboardPage';
import { AdminRoutesListPage } from './pages/admin/AdminRoutesListPage';
import { AdminVehiclesListPage } from './pages/admin/AdminVehiclesListPage';
import { AdminShipmentsListPage } from './pages/admin/AdminShipmentsListPage';
import { AdminCustomersListPage } from './pages/admin/AdminCustomersListPage';
import { AdminDriversListPage } from './pages/admin/AdminDriversListPage';
import { ReoptimizationPolicyPage } from './pages/admin/ReoptimizationPolicyPage';
import { OptimizationDashboardPage } from './pages/admin/OptimizationDashboardPage';
import { CustomerRouteDetailPage } from './pages/customer/CustomerRouteDetailPage';
import { CustomerDashboardPage } from './pages/customer/CustomerDashboardPage';
import { DriverRoutePage } from './pages/driver/DriverRoutePage';

export const router = createBrowserRouter([
  {
    path: '/app',
    element: <AppLayout />,
    children: [
      { path: 'admin/dashboard', element: <AdminDashboardPage /> },
      { path: 'admin/routes', element: <AdminRoutesListPage /> },
      { path: 'admin/vehicles', element: <AdminVehiclesListPage /> },
      { path: 'admin/shipments', element: <AdminShipmentsListPage /> },
      { path: 'admin/customers', element: <AdminCustomersListPage /> },
      { path: 'admin/drivers', element: <AdminDriversListPage /> },
      { path: 'admin/fleet-map', element: <FleetMapPage /> },
      { path: 'admin/exception-map', element: <ExceptionMapPage /> },
      { path: 'admin/routes/:publicId', element: <RouteDetailPage /> },
      { path: 'admin/test-routing', element: <TestRoutingPage /> },
      { path: 'admin/operator-dashboard', element: <OperatorDashboardPage /> },
      { path: 'admin/route-planner', element: <RoutePlannerPage /> },
      { path: 'admin/widgets', element: <WidgetGalleryPage /> },
      { path: 'admin/page-layouts', element: <PageLayoutEditorPage /> },
      { path: 'admin/routes/:publicId/analysis', element: <RouteAnalysisPage /> },
      { path: 'admin/reoptimization-policies', element: <ReoptimizationPolicyPage /> },
      { path: 'admin/optimization-dashboard', element: <OptimizationDashboardPage /> },
      { path: 'customer/dashboard', element: <CustomerDashboardPage /> },
      { path: 'customer/routes/:publicId', element: <CustomerRouteDetailPage /> },
      { path: 'driver/routes/:publicId', element: <DriverRoutePage /> },
      { index: true, element: <Navigate to="admin/dashboard" replace /> },
    ],
  },
]);
