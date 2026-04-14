import { useAdminDashboard } from '@/api/hooks/useAdminDashboard';
import { useMe } from '@/api/hooks/useMe';
import { usePageLayout } from '@/api/hooks/usePageLayout';
import { WidgetRenderer } from '@/components/bottom-sheet/WidgetRenderer';

/* ── Helpers ─────────────────────────────────────────────────────── */

function getGreeting(): string {
  const h = new Date().getHours();
  if (h < 12) return 'Buenos dias';
  if (h < 19) return 'Buenas tardes';
  return 'Buenas noches';
}

function formatDate(): string {
  return new Date().toLocaleDateString('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

function formatSecondsAgo(seconds: number | null): string {
  if (seconds === null) return 'Sin datos';
  if (seconds < 60) return `hace ${seconds}s`;
  if (seconds < 3600) return `hace ${Math.floor(seconds / 60)}min`;
  return `hace ${Math.floor(seconds / 3600)}h`;
}

/* ── Main Page ───────────────────────────────────────────────────── */

export function AdminDashboardPage() {
  const { data, isLoading: dashLoading, error: dashError } = useAdminDashboard();
  const { data: me } = useMe();
  const { layout, isLoading: layoutLoading, error: layoutError } = usePageLayout('admin_dashboard');

  const isLoading = dashLoading || layoutLoading;

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

  if (dashError || layoutError || !data) {
    return (
      <div className="p-8 text-center">
        <p style={{ color: 'var(--color-error)' }}>Error cargando dashboard</p>
      </div>
    );
  }

  const firstName = me?.email?.split('@')[0] ?? '';

  return (
    <div
      className="h-full overflow-y-auto"
      style={{ backgroundColor: 'var(--color-surface)' }}
    >
      <div
        className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6"
        style={{ gap: 'var(--section-gap)' }}
      >

        {/* ── Greeting Header (page chrome — not a widget) ── */}
        <div className="animate-fade-in-up">
          <h1
            className="text-2xl sm:text-3xl font-bold tracking-tight"
            style={{ color: 'var(--color-text-primary)' }}
          >
            {getGreeting()}{firstName ? `, ${firstName}` : ''}
          </h1>
          <p className="mt-1 text-sm" style={{ color: 'var(--color-text-secondary)' }}>
            {formatDate()} · Actualizado {formatSecondsAgo(data.live.last_ingestion.seconds_ago)}
          </p>
        </div>

        {/* ── Registry-driven widgets ── */}
        <WidgetRenderer
          layout={layout}
          sheetState="full"
          pageData={data}
          mode="page"
        />
      </div>
    </div>
  );
}
