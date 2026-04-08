import type { WidgetProps } from './types';

export function ReportsBannerWidget({ data: _data }: WidgetProps) {
  return (
    <a
      href="/admin/reports"
      className="group flex items-center justify-between rounded-xl p-5 shadow-sm transition-all hover:shadow-md"
      style={{ background: 'linear-gradient(to right, var(--color-accent), #6366f1)' }}
    >
      <div>
        <p className="text-sm font-semibold text-white">Reportes y Analitica</p>
        <p className="text-xs text-white/70">
          Accede a reportes detallados de entregas, transportistas y clientes
        </p>
      </div>
      <svg
        className="h-5 w-5 text-white/70 transition-transform group-hover:translate-x-1"
        fill="none"
        viewBox="0 0 24 24"
        strokeWidth="2"
        stroke="currentColor"
      >
        <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
      </svg>
    </a>
  );
}
