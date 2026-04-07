import { useMemo } from 'react';
import { useDashboardData } from '@/api/hooks/useDashboardData';
import { usePageLayout } from '@/api/hooks/usePageLayout';
import { WidgetRenderer } from '@/components/bottom-sheet/WidgetRenderer';

interface AdminDashboardPageProps {
  mercurePublicUrl?: string;
}

export function AdminDashboardPage({ mercurePublicUrl }: AdminDashboardPageProps) {
  const { health, live, metrics, lastRefresh, isLoading, error } = useDashboardData();
  const { layout } = usePageLayout('admin_dashboard');

  const pageData = useMemo(
    () => ({
      health,
      live,
      metrics,
      mercurePublicUrl,
    }),
    [health, live, metrics, mercurePublicUrl],
  );

  const refreshTime = lastRefresh
    ? new Date(lastRefresh).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
    : '';

  if (error) {
    return (
      <div className="rounded-xl bg-red-50 p-6 text-center">
        <p className="text-red-700 font-medium">Error cargando datos del dashboard</p>
        <p className="text-red-500 text-sm mt-1">{error.message}</p>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="text-center">
          <div className="animate-spin h-8 w-8 border-2 border-indigo-600 border-t-transparent rounded-full mx-auto mb-4" />
          <p className="text-sm text-gray-500">Cargando dashboard...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Refresh indicator */}
      {refreshTime && (
        <div className="flex justify-end">
          <span className="text-xs text-gray-400">Actualizado {refreshTime}</span>
        </div>
      )}

      {/* Widgets rendered via the configurable widget system */}
      <WidgetRenderer
        layout={layout}
        sheetState="half"
        pageData={pageData}
        mode="page"
      />

      {/* Reports banner — static, outside widget system */}
      <a
        href="/admin/reports"
        className="group flex items-center justify-between rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 p-5 shadow-sm transition-all hover:shadow-md"
      >
        <div className="flex items-center gap-4">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-white/20">
            <svg className="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-semibold text-white">Reportes y Analítica</p>
            <p className="text-xs text-white/70">Accede a reportes detallados de entregas, transportistas y clientes</p>
          </div>
        </div>
        <svg className="h-5 w-5 text-white/70 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
        </svg>
      </a>
    </div>
  );
}
