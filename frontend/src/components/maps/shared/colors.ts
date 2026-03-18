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
