import { useState } from 'react';
import type { FleetKpi } from '@/api/hooks/useFleetKpi';
import type { FleetVehicle, FleetRoute } from '@/api/types';
import { KpiPills } from './KpiPills';
import { VehicleList } from './VehicleList';
import { RouteList } from './RouteList';
import { RouteProgressBar } from './RouteProgressBar';

interface Props {
  vehicles: FleetVehicle[];
  routes: FleetRoute[];
  kpi: FleetKpi | undefined;
  selectedVehicleId: string | null;
  selectedRouteId: string | null;
  onSelectVehicle: (vehicle: FleetVehicle) => void;
  onSelectRoute: (route: FleetRoute) => void;
  /** Route associated with the currently selected vehicle */
  selectedVehicleRoute: FleetRoute | null;
}

export function FleetSidebar({
  vehicles,
  routes,
  kpi,
  selectedVehicleId,
  selectedRouteId,
  onSelectVehicle,
  onSelectRoute,
  selectedVehicleRoute,
}: Props) {
  const [activeTab, setActiveTab] = useState<'vehicles' | 'routes'>('vehicles');
  const [searchQuery, setSearchQuery] = useState('');

  return (
    <div className="flex flex-col w-80 border-r" style={{ backgroundColor: 'var(--color-surface-glass)', backdropFilter: 'blur(16px)', borderColor: 'var(--color-border-subtle)' }}>
      {/* Back link */}
      <a
        href="/admin"
        className="flex-shrink-0 flex items-center gap-2 px-5 pt-4 pb-2 text-slate-400 hover:text-white transition-colors text-sm font-medium"
      >
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Back to Dashboard
      </a>

      {/* Brand */}
      <div className="flex-shrink-0 px-5 pt-2 pb-3">
        <div className="flex items-center gap-2.5 mb-4">
          <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center">
            <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
          </div>
          <div>
            <h1 className="text-white font-bold text-base tracking-tight">MXO Track</h1>
            <p className="text-slate-500 text-[10px] uppercase tracking-widest">Fleet Overview</p>
          </div>
        </div>

        <div className="mb-4">
          <KpiPills kpi={kpi} />
        </div>

        {/* Tabs */}
        <div className="flex theme-card-overlay p-0.5 mb-3">
          <button
            onClick={() => setActiveTab('vehicles')}
            className={`flex-1 text-xs font-medium py-1.5 px-3 rounded-md transition-all ${
              activeTab === 'vehicles'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            Vehicles
          </button>
          <button
            onClick={() => setActiveTab('routes')}
            className={`flex-1 text-xs font-medium py-1.5 px-3 rounded-md transition-all ${
              activeTab === 'routes'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            Routes
          </button>
        </div>

        {/* Search (vehicles tab only) */}
        {activeTab === 'vehicles' && (
          <div className="relative">
            <svg
              className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={2}
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
              />
            </svg>
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search vehicles..."
              className="w-full rounded-lg pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-1"
              style={{
                backgroundColor: 'var(--color-surface-glass)',
                border: '1px solid var(--color-border-subtle)',
                color: 'var(--color-text-primary)',
              }}
            />
          </div>
        )}
      </div>

      {/* Scrollable list */}
      <div className="flex-1 overflow-y-auto px-3 pb-3">
        {activeTab === 'vehicles' ? (
          <VehicleList
            vehicles={vehicles}
            searchQuery={searchQuery}
            selectedId={selectedVehicleId}
            onSelect={onSelectVehicle}
          />
        ) : (
          <RouteList
            routes={routes}
            selectedId={selectedRouteId}
            onSelect={onSelectRoute}
          />
        )}
      </div>

      {/* Route progress bar (when vehicle with route is selected) */}
      {selectedVehicleRoute && (
        <div className="flex-shrink-0">
          <RouteProgressBar route={selectedVehicleRoute} />
        </div>
      )}
    </div>
  );
}
