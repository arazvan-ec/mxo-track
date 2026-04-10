// Navigation — GET /api/navigation
export interface NavItem {
  label: string;
  href: string;
  icon: string;
}

export interface NavSection {
  title: string;
  items: NavItem[];
}

export interface NavigationResponse {
  sections: NavSection[];
}

// Match backend DTOs

export interface MeResponse {
  publicId: string;
  email: string;
  role: string;
  customerId?: string;
  customerName?: string;
  locale?: string;
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
  shipmentPublicId?: string;
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
  publicId: string;
  name: string;
  color: string;
  vehicleName?: string;
  driverName?: string;
  status: string;
  totalStops: number;
  deliveredStops: number;
  polyline?: string;
  stops: FleetStop[];
}

export interface FleetStop {
  lat: number;
  lng: number;
  address: string;
  sequence: number;
  status: string;
  recipient?: string;
  recipientName?: string;
  recipientPhone?: string;
  deliveredAt?: string;
  exceptionCode?: string;
  exceptionNotes?: string;
  shipmentPublicId?: string;
  routePublicId?: string;
}

// ── Route Planner types ──────────────────────────────────────────────

export interface PlannerShipment {
  publicId: string;
  reference?: string;
  lat: number;
  lng: number;
  address: string;
  recipientName: string;
  totalWeightKg?: number;
  totalVolumeM3?: number;
  totalParcels?: number;
  addressRisk?: { is_risky: boolean; exception_rate?: number; sample_count?: number };
}

export interface PlannerVehicle {
  publicId: string;
  name: string;
  maxWeightKg?: number;
  maxVolumeM3?: number;
  maxParcels?: number;
}

export interface PlannerLocation {
  publicId: string;
  name: string;
  address?: string;
  isDefault: boolean;
}

export interface PlannerCluster {
  shipmentIds: string[];
  centroid: { lat: number; lng: number };
  color: string;
}

export interface PlannerPreviewRoute {
  route: {
    publicId: string;
    name: string;
    vehicle?: string;
    totalDistanceKm?: number;
    estimatedDurationMinutes?: number;
  };
  stops: Array<{
    latitude: number;
    longitude: number;
    address: string;
    recipientName?: string;
    sequence: number;
    isOrigin?: boolean;
  }>;
  polyline?: string;
  stopsCount: number;
  validation?: {
    valid: boolean;
    errors: string[];
    totalWeightKg?: number;
    maxWeightKg?: number;
    weightUtilization?: number;
    totalVolumeM3?: number;
    maxVolumeM3?: number;
    volumeUtilization?: number;
    totalParcels?: number;
    maxParcels?: number;
    parcelUtilization?: number;
  };
}

export interface PlannerPreviewResponse {
  routes: PlannerPreviewRoute[];
  optimizationLog?: unknown;
}

export interface DriverSuggestion {
  driver_public_id: string;
  driver_name: string;
  driver_email: string;
  score: number;
  breakdown: {
    zone: number;
    rating: number;
    workload: number;
    skills: number;
  };
  top_criterion: string;
}

export interface PlannerConfirmResponse {
  ok: boolean;
  assigned: number;
  errors: string[];
}

/* ── Admin Dashboard ──────────────────────────────────────────────── */

export interface HealthStatus {
  traccar_ok: boolean;
  mercure_ok: boolean;
  db_ok: boolean;
  redis_ok: boolean;
  osrm_ok: boolean;
  vroom_ok: boolean;
}

export interface LiveServiceData {
  ok: boolean;
  latency_ms: number;
}

export interface LiveData {
  database: LiveServiceData;
  redis: LiveServiceData;
  traccar: LiveServiceData;
  mercure: LiveServiceData;
  osrm: LiveServiceData & { has_geometry?: boolean };
  vroom: LiveServiceData;
  positions: { row_count: number; warning: boolean };
  disk: { db_size_mb: number };
  last_ingestion: { timestamp: string | null; seconds_ago: number | null };
}

export interface DashboardMetrics {
  active_routes: number;
  pending_stops: number;
  import_runs_today: number;
  positions_ingested_last_hour: number;
}

export interface DailyDelivery {
  date: string;
  deliveries: number;
}

export interface TopDriver {
  driver_name: string;
  driver_email: string;
  deliveries: number;
}

export interface AdminDashboardResponse {
  health: HealthStatus;
  live: LiveData;
  metrics: DashboardMetrics;
  daily_deliveries: DailyDelivery[];
  top_drivers: TopDriver[];
  generated_at: string;
}

/* ── Paginated List Types ──────────────────────────────────────────── */

export interface PaginatedResponse<T> {
  items: T[];
  total: number;
  page: number;
  pages: number;
}

export interface RouteListItem {
  publicId: string;
  name: string;
  customerName: string | null;
  vehicleName: string | null;
  driverName: string | null;
  driverEmail: string | null;
  status: 'PLANNED' | 'ACTIVE' | 'DONE' | 'CANCELLED';
  deliveredStops: number;
  totalStops: number;
}

export interface VehicleListItem {
  publicId: string;
  name: string;
  traccarDeviceId: number | null;
  active: boolean;
  maxWeightKg: number | null;
  maxVolumeM3: number | null;
  maxParcels: number | null;
  lastPosition: { lat: number; lng: number } | null;
  createdAt: string;
}

export interface ShipmentListItem {
  publicId: string;
  reference: string | null;
  customerName: string | null;
  recipientName: string;
  address: string;
  priority: 'CRITICAL' | 'URGENT' | 'HIGH' | 'NORMAL' | 'LOW';
  totalWeightKg: number | null;
  totalVolumeM3: number | null;
  totalParcels: number | null;
  createdAt: string;
}

export interface CustomerListItem {
  publicId: string;
  name: string;
  address: string | null;
  email: string | null;
  phone: string | null;
  active: boolean;
  userCount: number;
}

export interface DriverListItem {
  publicId: string;
  email: string;
  name: string | null;
  active: boolean;
  createdAt: string;
}

export interface RouteFilterOptions {
  drivers: Array<{ id: number; name: string; email: string }>;
  customers: Array<{ id: number; name: string }>;
}

export interface OptimizerComparisonResult {
  optimizer_name: string;
  distance_km: number;
  duration_min: number;
  route_count: number;
  unassigned_count: number;
}

/* ── Optimization Analytics ─────────────────────────────────────── */

export interface OptimizerMetric {
  optimizer_name: string;
  avg_distance_km: number;
  avg_duration_min: number;
  route_count: number;
  avg_success_rate: number;
}

export interface AddressRiskInfo {
  address: string;
  total_deliveries: number;
  exception_count: number;
  exception_rate: number;
  is_high_risk: boolean;
}

export interface ReoptEvent {
  route_public_id: string;
  trigger: string;
  occurred_at: string;
}

/* ── Reoptimization Policy ───────────────────────────────────────── */

export interface ReoptimizationPolicy {
  public_id: string;
  triggers: string[];
  delay_threshold_minutes: number;
  cooldown_minutes: number;
  consecutive_exception_threshold: number;
  enabled: boolean;
}
