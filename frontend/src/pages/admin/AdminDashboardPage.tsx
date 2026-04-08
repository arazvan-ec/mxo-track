import { useMemo } from 'react';
import { useAdminDashboard } from '@/api/hooks/useAdminDashboard';
import { usePageLayout } from '@/api/hooks/usePageLayout';
import { WidgetRenderer } from '@/components/bottom-sheet/WidgetRenderer';

export function AdminDashboardPage() {
  const { data, isLoading, error } = useAdminDashboard();
  const { layout } = usePageLayout('admin_dashboard');

  const pageData = useMemo(
    () => ({
      health: data?.health,
      live: data?.live,
      metrics: data?.metrics,
      daily_deliveries: data?.daily_deliveries,
      top_drivers: data?.top_drivers,
    }),
    [data],
  );

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div
          className="animate-spin h-8 w-8 border-2 rounded-full border-t-transparent"
          style={{ borderColor: 'var(--color-accent)', borderTopColor: 'transparent' }}
        />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="p-8 text-center">
        <p style={{ color: 'var(--color-error)' }}>Error cargando dashboard</p>
      </div>
    );
  }

  return (
    <div className="h-full overflow-y-auto p-6 lg:p-8 space-y-8" style={{ backgroundColor: 'var(--color-surface)' }}>
      {/* Page header */}
      <div>
        <h1 className="text-2xl font-bold tracking-tight" style={{ color: 'var(--color-text-primary)' }}>
          Dashboard
        </h1>
        <p className="mt-1 text-sm" style={{ color: 'var(--color-text-secondary)' }}>
          Vista general del sistema de seguimiento logistico
        </p>
      </div>

      {/* Widget-system rendered sections — each collapsible */}
      <WidgetRenderer layout={layout} sheetState="full" pageData={pageData} mode="page" />
    </div>
  );
}
