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
  pending: '#3B82F6',    // blue
  delivered: '#10B981',  // green
  exception: '#EF4444',  // red
  skipped: '#9CA3AF',    // gray
};

export const VEHICLE_COLOR = '#1D4ED8'; // blue-700
export const ORIGIN_COLOR = '#10B981';  // green
