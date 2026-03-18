// Match COLORS from backend/templates/components/route/_map_js.html.twig
export const ROUTE_COLORS = [
  '#3B82F6', // blue
  '#10B981', // green
  '#F59E0B', // amber
  '#EF4444', // red
  '#8B5CF6', // violet
  '#EC4899', // pink
] as const;

export const STOP_STATUS_COLORS: Record<string, string> = {
  PENDING: '#3B82F6',    // blue
  DELIVERED: '#10B981',  // green
  EXCEPTION: '#EF4444',  // red
  SKIPPED: '#9CA3AF',    // gray
  // Lowercase aliases for safety
  pending: '#3B82F6',
  delivered: '#10B981',
  exception: '#EF4444',
  skipped: '#9CA3AF',
};

export const VEHICLE_COLOR = '#1D4ED8'; // blue-700
export const ORIGIN_COLOR = '#10B981';  // green

export const SKILL_COLORS: Record<string, string> = {
  REFRIGERATED: '#0ea5e9',
  HEAVY_LOAD: '#f97316',
  PEDESTRIAN_ACCESS: '#22c55e',
  HAZMAT: '#ef4444',
  FRAGILE: '#ec4899',
};

export const DEFAULT_MARKER_COLOR = '#6366f1'; // indigo

/** Get vehicle color from marker_color, first skill, or default */
export function getVehicleColor(vehicle: { marker_color?: string; skills?: string[] }): string {
  if (vehicle.marker_color) return vehicle.marker_color;
  if (vehicle.skills?.[0]) return SKILL_COLORS[vehicle.skills[0]] ?? DEFAULT_MARKER_COLOR;
  return DEFAULT_MARKER_COLOR;
}
