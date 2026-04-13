import { describe, it, expect } from 'vitest';
import { ADMIN_DASHBOARD_LAYOUT } from './adminDashboardLayout';

describe('ADMIN_DASHBOARD_LAYOUT', () => {
  const fullWidgets = ADMIN_DASHBOARD_LAYOUT.widgets.full;

  it('exposes 6 widgets in the full state', () => {
    expect(fullWidgets).toHaveLength(6);
  });

  it('has widgets in the correct order', () => {
    const types = fullWidgets.map((w) => w.type);
    expect(types).toEqual([
      'dashboard_kpis',
      'system_health',
      'mini_reports',
      'infrastructure_metrics',
      'activity_feed',
      'reports_banner',
    ]);
  });

  it('positions are sequential starting from 0', () => {
    fullWidgets.forEach((w, i) => {
      expect(w.position).toBe(i);
    });
  });

  it('has pageKey admin_dashboard', () => {
    expect(ADMIN_DASHBOARD_LAYOUT.pageKey).toBe('admin_dashboard');
  });

  it('collapsed and half states are empty', () => {
    expect(ADMIN_DASHBOARD_LAYOUT.widgets.collapsed).toEqual([]);
    expect(ADMIN_DASHBOARD_LAYOUT.widgets.half).toEqual([]);
  });
});
