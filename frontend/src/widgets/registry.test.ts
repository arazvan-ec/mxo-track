import { describe, it, expect } from 'vitest';
import { WIDGET_REGISTRY } from './registry';

describe('WIDGET_REGISTRY dashboard widget metadata', () => {
  const detailWidgets = [
    'dashboard_kpis',
    'system_health',
    'mini_reports',
    'infrastructure_metrics',
    'activity_feed',
  ] as const;

  it.each(detailWidgets)('%s has supportsDetail: true', (type) => {
    expect(WIDGET_REGISTRY[type].supportsDetail).toBe(true);
  });

  it('activity_feed has defaultMinimized: true', () => {
    expect(WIDGET_REGISTRY.activity_feed.defaultMinimized).toBe(true);
  });

  it('reports_banner does NOT have supportsDetail', () => {
    expect(WIDGET_REGISTRY.reports_banner.supportsDetail).toBeUndefined();
  });
});
