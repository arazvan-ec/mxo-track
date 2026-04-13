import type { LayoutConfig } from '@/types/layout';

/**
 * Hardcoded layout for AdminDashboardPage.
 *
 * SheetStateName only allows 'collapsed' | 'half' | 'full'.
 * For page mode, we use 'full' as the key because:
 *  - AdminDashboardPage always renders all widgets (no bottom-sheet states)
 *  - WidgetRenderer reads layout.widgets[sheetState], so the page passes sheetState='full'
 *  - 'collapsed' and 'half' are left empty since page mode doesn't use them
 */
export const ADMIN_DASHBOARD_LAYOUT: LayoutConfig = {
  pageKey: 'admin_dashboard',
  scope: 'global',
  widgets: {
    collapsed: [],
    half: [],
    full: [
      { type: 'dashboard_kpis', position: 0 },
      { type: 'system_health', position: 1 },
      { type: 'mini_reports', position: 2 },
      { type: 'infrastructure_metrics', position: 3 },
      { type: 'activity_feed', position: 4 },
      { type: 'reports_banner', position: 5 },
    ],
  },
};
