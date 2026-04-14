import { useState } from 'react';
import type { WidgetProps } from './types';
import { AnimatedCounter } from '@/components/ui/AnimatedCounter';
import { SparklineSVG } from '@/components/ui/SparklineSVG';
import { useActiveRoutes } from '@/api/hooks/useActiveRoutes';

interface DashboardKpisData {
  metrics?: {
    active_routes: number;
    pending_stops: number;
    import_runs_today: number;
    positions_ingested_last_hour: number;
  };
  daily_deliveries?: { date: string; deliveries: number }[];
}

interface KpiCardProps {
  label: string;
  value: number;
  sparkData?: number[];
  accentColor: string;
  icon: React.ReactNode;
  delay: number;
}

function KpiCard({ label, value, sparkData, accentColor, icon, delay }: KpiCardProps) {
  return (
    <div
      className="theme-card relative overflow-hidden animate-fade-in-up"
      style={{ padding: 'var(--card-padding)', animationDelay: `${delay}ms` }}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text-secondary)' }}>
            {label}
          </p>
          <div className="mt-1 flex items-baseline gap-2">
            <AnimatedCounter
              value={value}
              className="text-2xl font-bold tracking-tight tabular-nums"
              style={{ color: 'var(--color-text-primary)', fontFamily: 'var(--kpi-font)' }}
            />
            {sparkData && sparkData.length >= 2 && (
              <SparklineSVG data={sparkData} color={accentColor} width={64} height={20} />
            )}
          </div>
        </div>
        <div
          className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
          style={{ backgroundColor: `${accentColor}18`, color: accentColor }}
        >
          {icon}
        </div>
      </div>
      {/* Bottom accent bar */}
      <div
        className="absolute bottom-0 left-0 right-0 h-0.5"
        style={{ background: `linear-gradient(to right, ${accentColor}, transparent)` }}
      />
    </div>
  );
}

function ExpandableRouteCard({ value, accentColor }: { value: number; accentColor: string }) {
  const [expanded, setExpanded] = useState(false);
  const { data, isLoading } = useActiveRoutes(expanded);
  const routes = data?.items ?? [];

  return (
    <div
      className="theme-card relative overflow-hidden animate-fade-in-up"
      style={{ animationDelay: '0ms' }}
    >
      <button
        type="button"
        className="w-full text-left"
        style={{ padding: 'var(--card-padding)' }}
        onClick={() => setExpanded((p) => !p)}
      >
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text-secondary)' }}>
              Rutas activas
            </p>
            <div className="mt-1 flex items-baseline gap-2">
              <AnimatedCounter
                value={value}
                className="text-2xl font-bold tracking-tight tabular-nums"
                style={{ color: 'var(--color-text-primary)', fontFamily: 'var(--kpi-font)' }}
              />
            </div>
          </div>
          <div className="flex items-center gap-2">
            <div
              className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
              style={{ backgroundColor: `${accentColor}18`, color: accentColor }}
            >
              <RouteIcon />
            </div>
            <svg
              className={`h-4 w-4 transition-transform duration-200 ${expanded ? 'rotate-180' : ''}`}
              style={{ color: 'var(--color-text-muted)' }}
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={2}
              stroke="currentColor"
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </div>
        </div>
      </button>

      {/* Expandable route list */}
      <div
        className={`transition-all duration-200 ease-in-out overflow-hidden ${
          expanded ? 'max-h-[600px] opacity-100' : 'max-h-0 opacity-0'
        }`}
      >
        <div
          className="px-4 pb-3 space-y-2"
          style={{ borderTop: '1px solid var(--color-border)' }}
        >
          {isLoading && (
            <div className="flex justify-center py-3">
              <div
                className="animate-spin h-5 w-5 border-2 rounded-full border-t-transparent"
                style={{ borderColor: accentColor, borderTopColor: 'transparent' }}
              />
            </div>
          )}
          {!isLoading && routes.length === 0 && (
            <p className="text-xs py-2" style={{ color: 'var(--color-text-muted)' }}>
              Sin rutas activas
            </p>
          )}
          {routes.map((route) => (
            <div
              key={route.publicId}
              className="flex items-center gap-3 py-2"
              style={{ borderBottom: '1px solid var(--color-border-light, var(--color-border))' }}
            >
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium truncate" style={{ color: 'var(--color-text-primary)' }}>
                  {route.name}
                </p>
                <p className="text-xs truncate" style={{ color: 'var(--color-text-secondary)' }}>
                  {route.driverName ?? route.driverEmail ?? 'Sin conductor'}
                  {route.vehicleName ? ` · ${route.vehicleName}` : ''}
                </p>
              </div>
              <div className="shrink-0 flex items-center gap-2">
                <div className="w-16 h-1.5 rounded-full overflow-hidden" style={{ backgroundColor: 'var(--color-border)' }}>
                  <div
                    className="h-full rounded-full transition-all"
                    style={{
                      width: route.totalStops > 0 ? `${(route.deliveredStops / route.totalStops) * 100}%` : '0%',
                      backgroundColor: accentColor,
                    }}
                  />
                </div>
                <span className="text-xs tabular-nums" style={{ color: 'var(--color-text-secondary)' }}>
                  {route.deliveredStops}/{route.totalStops}
                </span>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Bottom accent bar */}
      <div
        className="absolute bottom-0 left-0 right-0 h-0.5"
        style={{ background: `linear-gradient(to right, ${accentColor}, transparent)` }}
      />
    </div>
  );
}

const RouteIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
  </svg>
);

const ClockIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
  </svg>
);

const UploadIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
  </svg>
);

const SignalIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 004.5 9.75v7.5a2.25 2.25 0 002.25 2.25h7.5a2.25 2.25 0 002.25-2.25v-7.5a2.25 2.25 0 00-2.25-2.25h-.75m0-3l-3-3m0 0l-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 012.25 2.25v7.5a2.25 2.25 0 01-2.25 2.25h-7.5a2.25 2.25 0 01-2.25-2.25v-.75" />
  </svg>
);

export function DashboardKpisSummary({ data }: WidgetProps) {
  const { metrics } = data as DashboardKpisData;
  if (!metrics) return null;
  return (
    <span className="text-xs tabular-nums" style={{ color: 'var(--color-text-secondary)' }}>
      <span className="font-semibold" style={{ color: 'var(--color-text-primary)' }}>
        {metrics.active_routes}
      </span>{' '}rutas ·{' '}
      <span className="font-semibold" style={{ color: 'var(--color-text-primary)' }}>
        {metrics.pending_stops}
      </span>{' '}paradas
    </span>
  );
}

export function DashboardKpisWidget({ data }: WidgetProps) {
  const { metrics, daily_deliveries } = data as DashboardKpisData;
  if (!metrics) return null;

  const sparkData = daily_deliveries?.map((d) => d.deliveries);

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" style={{ gap: 'var(--section-gap, 1rem)' }}>
      <ExpandableRouteCard value={metrics.active_routes} accentColor="var(--color-accent)" />
      <KpiCard label="Paradas pendientes" value={metrics.pending_stops} accentColor="var(--color-warning)" icon={<ClockIcon />} delay={60} />
      <KpiCard label="Imports CSV hoy" value={metrics.import_runs_today} accentColor="#8b5cf6" icon={<UploadIcon />} delay={120} />
      <KpiCard label="Posiciones (ultima hora)" value={metrics.positions_ingested_last_hour} sparkData={sparkData} accentColor="#06b6d4" icon={<SignalIcon />} delay={180} />
    </div>
  );
}
