// Match backend DTOs

export interface MeResponse {
  publicId: string;
  email: string;
  role: string;
  customerId?: string;
  customerName?: string;
}

// Match MapViewData.toArray() — src/View/MapViewData.php
export interface MapData {
  routes: RouteData[];
  origin?: { lat: number; lng: number; address?: string };
  globalMetrics?: Record<string, unknown>;
  mercureTopic?: string;
  mercureUrl?: string;
  vehiclePublicId?: string;
  vehiclePosition?: {
    lat: number;
    lng: number;
    speed?: number;
    course?: number;
  };
}

// Match RouteViewData.toArray() — src/View/RouteViewData.php
export interface RouteData {
  publicId: string;
  name: string;
  color: string;
  vehicleName?: string;
  driverName?: string;
  status?: string;
  stops: StopData[];
  polyline?: string;
  metrics?: Record<string, unknown>;
  timing?: Record<string, unknown>;
  validation?: Record<string, unknown>;
  originalStops?: StopData[];
  comparisonPolyline?: string;
}

// Match StopViewData.toArray() — src/View/StopViewData.php
export interface StopData {
  sequence: number;
  address: string;
  recipientName?: string;
  recipientPhone?: string;
  lat?: number;
  lng?: number;
  status: string;
  isOrigin: boolean;
  deliveredAt?: string;
  exceptionCode?: string;
  exceptionNotes?: string;
  etaMinutes?: number;
  etaTime?: string;
  etaDistanceKm?: number;
}

// Match MapUpdate VO — src/Domain/MapView/Model/MapUpdate.php
export interface MapUpdate {
  type: MapUpdateType;
  routePublicId: string;
  data: Record<string, unknown>;
  occurredAt: string;
}

export type MapUpdateType =
  | 'stop_delivered'
  | 'eta_changed'
  | 'deviation_detected'
  | 'route_started'
  | 'route_completed'
  | 'stop_exception'
  | 'route_snapshot'
  | 'route_optimized'
  | 'route_assigned'
  | 'route_cancelled'
  | 'routes_built'
  | 'deviation_ended';

// Match VehiclePosition VO — src/Domain/MapView/Model/VehiclePosition.php
export interface VehiclePosition {
  vehiclePublicId: string;
  lat: number;
  lng: number;
  speed?: number;
  course?: number;
  deviceTime: string;
}

// Fleet map data from GET /api/fleet/map-data
export interface FleetMapData {
  vehicles: FleetVehicle[];
  routes: FleetRoute[];
}

export interface FleetVehicle {
  public_id: string;
  name: string;
  skills: string[];
  marker_color: string;
  route_name?: string;
  driver_name?: string;
  last_position?: {
    lat: number;
    lng: number;
    speed?: number;
    course?: number;
    device_time?: string;
  };
}

export interface FleetRoute {
  public_id: string;
  name: string;
  color: string;
  vehicle_name?: string;
  driver_name?: string;
  status: string;
  total_stops: number;
  delivered_stops: number;
  stops: FleetStop[];
}

export interface FleetStop {
  lat: number;
  lng: number;
  address: string;
  sequence: number;
  status: string;
  recipient?: string;
}
