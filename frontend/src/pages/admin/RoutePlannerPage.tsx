import { useState, useCallback, useRef, useEffect, useMemo } from 'react';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { ShipmentClusterLayer } from '@/components/maps/layers/ShipmentClusterLayer';
import { StopMarker } from '@/components/maps/shared/StopMarker';
import { OriginMarker } from '@/components/maps/shared/OriginMarker';
import { ROUTE_COLORS } from '@/components/maps/shared/colors';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { DualMenuShell } from '@/components/layout/DualMenuShell';
import {
  usePlannerShipments,
  usePlannerImportShipments,
  usePlannerVehicles,
  usePlannerLocations,
  useClusterMutation,
  usePreviewMutation,
  useConfirmMutation,
} from '@/api/hooks/useRoutePlanner';
import type {
  PlannerShipment,
  PlannerVehicle,
  PlannerLocation,
  PlannerCluster,
  PlannerPreviewRoute,
  DriverSuggestion,
} from '@/api/types';

type Step = 1 | 2 | 3 | 4;

const STEP_LABELS = [
  'Seleccionar envios',
  'Configurar',
  'Vista previa',
  'Confirmar',
] as const;

export function RoutePlannerPage() {
  const [step, setStep] = useState<Step>(1);
  const mapRef = useRef<MapCanvasHandle>(null);

  // Read import_id from URL params
  const urlParams = useMemo(() => new URLSearchParams(window.location.search), []);
  const importId = urlParams.get('import_id') ?? undefined;

  // Step 1 state
  const [customerId, setCustomerId] = useState('');
  const [selectedShipmentIds, setSelectedShipmentIds] = useState<Set<string>>(new Set());
  const [clusters, setClusters] = useState<PlannerCluster[]>([]);
  const [numClusters, setNumClusters] = useState(3);

  // Step 2 state
  const [selectedVehicleIds, setSelectedVehicleIds] = useState<Set<string>>(new Set());
  const [originPublicId, setOriginPublicId] = useState('');
  const [maxStopsPerRoute, setMaxStopsPerRoute] = useState(30);

  // Step 3 state
  const [previewRoutes, setPreviewRoutes] = useState<PlannerPreviewRoute[]>([]);
  const [driverAssignments, setDriverAssignments] = useState<Record<string, string>>({});
  const [driverSuggestions, setDriverSuggestions] = useState<Record<string, DriverSuggestion[]>>({});
  const [loadingDrivers, setLoadingDrivers] = useState(false);

  // Queries
  const shipmentsQuery = usePlannerShipments(customerId || undefined);
  const importShipmentsQuery = usePlannerImportShipments(importId);
  const vehiclesQuery = usePlannerVehicles();
  const locationsQuery = usePlannerLocations(customerId || undefined);

  // Mutations
  const clusterMutation = useClusterMutation();
  const previewMutation = usePreviewMutation();
  const confirmMutation = useConfirmMutation();

  // When import_id is provided, use import shipments; otherwise use regular shipments
  const shipments = importId
    ? (importShipmentsQuery.data ?? [])
    : (shipmentsQuery.data ?? []);
  const vehicles = vehiclesQuery.data ?? [];
  const locations = locationsQuery.data ?? [];

  // Auto-load shipments from import when import_id is in URL
  const importAutoLoaded = useRef(false);
  useEffect(() => {
    if (importId && !importAutoLoaded.current) {
      importAutoLoaded.current = true;
      importShipmentsQuery.refetch().then((result) => {
        if (result.data) {
          setSelectedShipmentIds(new Set(result.data.map((s) => s.publicId)));
        }
      });
    }
  }, [importId]);

  // Fit map bounds when shipments load
  useEffect(() => {
    if (shipments.length > 0) {
      const points = shipments
        .filter((s) => s.lat && s.lng)
        .map((s) => ({ lat: s.lat, lng: s.lng }));
      if (points.length > 0) {
        setTimeout(() => mapRef.current?.fitBounds(points), 200);
      }
    }
  }, [shipments]);

  // -- Step 1 handlers --

  const handleLoadShipments = useCallback(() => {
    const query = importId ? importShipmentsQuery : shipmentsQuery;
    query.refetch().then((result) => {
      if (result.data) {
        setSelectedShipmentIds(new Set(result.data.map((s) => s.publicId)));
        setClusters([]);
      }
    });
  }, [importId, importShipmentsQuery, shipmentsQuery]);

  const handleToggleShipment = useCallback((publicId: string) => {
    setSelectedShipmentIds((prev) => {
      const next = new Set(prev);
      if (next.has(publicId)) {
        next.delete(publicId);
      } else {
        next.add(publicId);
      }
      return next;
    });
  }, []);

  const handleToggleAllShipments = useCallback(
    (checked: boolean) => {
      setSelectedShipmentIds(checked ? new Set(shipments.map((s) => s.publicId)) : new Set());
    },
    [shipments],
  );

  const handleCluster = useCallback(() => {
    if (selectedShipmentIds.size < 2) return;
    clusterMutation.mutate(
      { shipment_ids: Array.from(selectedShipmentIds), num_clusters: numClusters },
      { onSuccess: (data) => setClusters(data.clusters) },
    );
  }, [selectedShipmentIds, numClusters, clusterMutation]);

  // -- Step 2 handlers --

  const handleGoToStep2 = useCallback(() => {
    setStep(2);
    vehiclesQuery.refetch().then((result) => {
      if (result.data) {
        setSelectedVehicleIds(new Set(result.data.map((v) => v.publicId)));
      }
    });
    locationsQuery.refetch().then((result) => {
      if (result.data) {
        const defaultLoc = result.data.find((l) => l.isDefault);
        if (defaultLoc) setOriginPublicId(defaultLoc.publicId);
      }
    });
  }, [vehiclesQuery, locationsQuery]);

  const handleToggleVehicle = useCallback((publicId: string) => {
    setSelectedVehicleIds((prev) => {
      const next = new Set(prev);
      if (next.has(publicId)) {
        next.delete(publicId);
      } else {
        next.add(publicId);
      }
      return next;
    });
  }, []);

  const handleToggleAllVehicles = useCallback(
    (checked: boolean) => {
      setSelectedVehicleIds(checked ? new Set(vehicles.map((v) => v.publicId)) : new Set());
    },
    [vehicles],
  );

  // -- Step 3 handlers --

  const handleGeneratePreview = useCallback(() => {
    previewMutation.mutate(
      {
        shipment_ids: Array.from(selectedShipmentIds),
        vehicle_ids: Array.from(selectedVehicleIds),
        origin_public_id: originPublicId || null,
        max_stops_per_route: maxStopsPerRoute,
      },
      {
        onSuccess: (data) => {
          setPreviewRoutes(data.routes);
          setDriverAssignments({});
          setDriverSuggestions({});
          setStep(3);

          // Fit map to preview stops
          const points: Array<{ lat: number; lng: number }> = [];
          data.routes.forEach((r) =>
            r.stops.forEach((s) => {
              if (s.latitude && s.longitude) points.push({ lat: s.latitude, lng: s.longitude });
            }),
          );
          if (points.length > 0) {
            setTimeout(() => mapRef.current?.fitBounds(points), 200);
          }

          // Load driver suggestions for each route
          loadAllDriverSuggestions(data.routes);
        },
      },
    );
  }, [selectedShipmentIds, selectedVehicleIds, originPublicId, maxStopsPerRoute, previewMutation]);

  const loadAllDriverSuggestions = useCallback(async (routes: PlannerPreviewRoute[]) => {
    setLoadingDrivers(true);
    const suggestions: Record<string, DriverSuggestion[]> = {};
    const assignments: Record<string, string> = {};

    for (const routeData of routes) {
      const routeId = routeData.route.publicId;
      try {
        const resp = await fetch(
          `/admin/route-planner/suggest-drivers?route_id=${encodeURIComponent(routeId)}`,
          { credentials: 'same-origin', headers: { Accept: 'application/json' } },
        );
        if (resp.ok) {
          const data: DriverSuggestion[] = await resp.json();
          suggestions[routeId] = data;
          if (data.length > 0) {
            assignments[routeId] = data[0].driver_public_id;
          }
        }
      } catch {
        // skip this route
      }
    }

    setDriverSuggestions(suggestions);
    setDriverAssignments(assignments);
    setLoadingDrivers(false);
  }, []);

  // -- Step 4 (Confirm) --

  const handleConfirm = useCallback(() => {
    confirmMutation.mutate(
      { driver_assignments: driverAssignments },
      {
        onSuccess: () => {
          window.location.href = '/admin/routes';
        },
      },
    );
  }, [driverAssignments, confirmMutation]);

  const handleCancel = useCallback(() => {
    setPreviewRoutes([]);
    setDriverSuggestions({});
    setDriverAssignments({});
    setStep(1);
  }, []);

  // Derive origin location for map
  const originLocation = useMemo(() => {
    if (!originPublicId) return null;
    return locations.find((l) => l.publicId === originPublicId) ?? null;
  }, [originPublicId, locations]);

  // (Preview route polylines come from backend via previewRoutes[].polyline)

  // Get shipment cluster color
  const getShipmentClusterColor = useCallback(
    (publicId: string): string | null => {
      for (const cluster of clusters) {
        if (cluster.shipmentIds.includes(publicId)) {
          return cluster.color;
        }
      }
      return null;
    },
    [clusters],
  );

  const sidebar = (
    <div className="flex flex-col h-full overflow-hidden">
      {/* Header */}
      <div className="flex-shrink-0 px-5 pt-4 pb-3">
        <div className="flex items-center gap-2.5 mb-4">
          <div className="w-8 h-8 rounded-lg bg-purple-600 flex items-center justify-center">
            <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
            </svg>
          </div>
          <div>
            <h1 className="text-white font-bold text-base tracking-tight">Route Planner</h1>
            <p className="text-slate-500 text-[10px] uppercase tracking-widest">Build optimized routes</p>
          </div>
        </div>

        {/* Import mode banner */}
        {importId && (
          <div className="mb-2 rounded-lg bg-emerald-900/40 border border-emerald-700/40 px-3 py-2">
            <p className="text-xs font-medium text-emerald-300">
              Modo importacion CSV — envios pre-seleccionados
            </p>
          </div>
        )}

        {/* Step indicator */}
        <StepIndicator currentStep={step} />
      </div>

      {/* Scrollable content */}
      <div className="flex-1 overflow-y-auto px-4 pb-4">
        {step === 1 && (
          <Step1Panel
              customerId={customerId}
              onCustomerIdChange={setCustomerId}
              shipments={shipments}
              selectedShipmentIds={selectedShipmentIds}
              isLoading={importId ? importShipmentsQuery.isFetching : shipmentsQuery.isFetching}
              clusters={clusters}
              numClusters={numClusters}
              isClustering={clusterMutation.isPending}
              onLoadShipments={handleLoadShipments}
              onToggleShipment={handleToggleShipment}
              onToggleAll={handleToggleAllShipments}
              onNumClustersChange={setNumClusters}
              onCluster={handleCluster}
              onClearClusters={() => setClusters([])}
              onNext={handleGoToStep2}
              getClusterColor={getShipmentClusterColor}
            />
          )}

          {step === 2 && (
            <Step2Panel
              vehicles={vehicles}
              selectedVehicleIds={selectedVehicleIds}
              isLoading={vehiclesQuery.isFetching}
              locations={locations}
              originPublicId={originPublicId}
              maxStopsPerRoute={maxStopsPerRoute}
              isGenerating={previewMutation.isPending}
              onToggleVehicle={handleToggleVehicle}
              onToggleAll={handleToggleAllVehicles}
              onOriginChange={setOriginPublicId}
              onMaxStopsChange={setMaxStopsPerRoute}
              onBack={() => setStep(1)}
              onGenerate={handleGeneratePreview}
            />
          )}

          {(step === 3 || step === 4) && (
            <Step3Panel
              routes={previewRoutes}
              driverSuggestions={driverSuggestions}
              driverAssignments={driverAssignments}
              loadingDrivers={loadingDrivers}
              isConfirming={confirmMutation.isPending}
              onDriverAssign={(routeId, driverId) =>
                setDriverAssignments((prev) => ({ ...prev, [routeId]: driverId }))
              }
              onBack={() => setStep(2)}
              onCancel={handleCancel}
              onConfirm={handleConfirm}
            />
          )}
        </div>
      </div>
  );

  return (
    <DualMenuShell dataSidebar={sidebar} dataSidebarWidth="w-96">
      {/* Map */}
      <MapCanvas ref={mapRef}>
          {/* Step 1 & 2: Show shipment markers */}
          {(step === 1 || step === 2) && shipments.length > 0 && (
            <ShipmentClusterLayer
              shipments={shipments}
              clusters={clusters}
              selectedShipmentIds={selectedShipmentIds}
              onShipmentClick={handleToggleShipment}
            />
          )}

          {/* Step 2: Origin marker */}
          {step === 2 && originLocation && (
            <OriginMarker
              lng={0} // locations don't have lat/lng in the API response used here
              lat={0}
              address={originLocation.name}
            />
          )}

          {/* Step 3: Preview route polylines */}
          {step >= 3 && previewRoutes.map((routeData, idx) => {
            if (!routeData.polyline) return null;
            const color = ROUTE_COLORS[idx % ROUTE_COLORS.length];
            return (
              <RoutePolylineLayer
                key={routeData.route.publicId}
                id={`preview-${idx}`}
                polyline={routeData.polyline}
                color={color}
              />
            );
          })}

          {/* Step 3: Stop markers for preview routes */}
          {step >= 3 &&
            previewRoutes.map((routeData) => {
              return routeData.stops
                .filter((s) => s.latitude && s.longitude && !s.isOrigin)
                .map((s) => (
                  <StopMarker
                    key={`${routeData.route.publicId}-${s.sequence}`}
                    lng={s.longitude}
                    lat={s.latitude}
                    sequence={s.sequence}
                    status="PENDING"
                    address={s.address}
                  />
                ));
            })}

          {/* Step 3: Origin marker from preview stops */}
          {step >= 3 && (() => {
            for (const routeData of previewRoutes) {
              const originStop = routeData.stops.find((s) => s.isOrigin);
              if (originStop && originStop.latitude && originStop.longitude) {
                return (
                  <OriginMarker
                    lng={originStop.longitude}
                    lat={originStop.latitude}
                    address={originStop.address}
                  />
                );
              }
            }
            return null;
          })()}
        </MapCanvas>
    </DualMenuShell>
  );
}

// ── Step Indicator ──────────────────────────────────────────────────

function StepIndicator({ currentStep }: { currentStep: Step }) {
  return (
    <div className="flex items-center gap-1">
      {STEP_LABELS.map((label, idx) => {
        const stepNum = (idx + 1) as Step;
        const isCompleted = currentStep > stepNum;
        const isActive = currentStep === stepNum;

        return (
          <div key={label} className="flex items-center">
            <div className="flex items-center gap-1.5">
              <div
                className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                  isCompleted
                    ? 'bg-green-500 text-white'
                    : isActive
                      ? 'bg-blue-600 text-white'
                      : 'bg-slate-700 text-slate-400'
                }`}
              >
                {isCompleted ? (
                  <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={3} stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                ) : (
                  stepNum
                )}
              </div>
              <span
                className={`text-xs font-medium ${
                  isActive ? 'text-white' : 'text-slate-500'
                }`}
              >
                {label}
              </span>
            </div>
            {idx < STEP_LABELS.length - 1 && (
              <svg className="w-4 h-4 mx-1 text-slate-600" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
              </svg>
            )}
          </div>
        );
      })}
    </div>
  );
}

// ── Step 1 Panel ────────────────────────────────────────────────────

interface Step1Props {
  customerId: string;
  onCustomerIdChange: (v: string) => void;
  shipments: PlannerShipment[];
  selectedShipmentIds: Set<string>;
  isLoading: boolean;
  clusters: PlannerCluster[];
  numClusters: number;
  isClustering: boolean;
  onLoadShipments: () => void;
  onToggleShipment: (id: string) => void;
  onToggleAll: (checked: boolean) => void;
  onNumClustersChange: (v: number) => void;
  onCluster: () => void;
  onClearClusters: () => void;
  onNext: () => void;
  getClusterColor: (id: string) => string | null;
}

function Step1Panel({
  customerId,
  onCustomerIdChange,
  shipments,
  selectedShipmentIds,
  isLoading,
  clusters,
  numClusters,
  isClustering,
  onLoadShipments,
  onToggleShipment,
  onToggleAll,
  onNumClustersChange,
  onCluster,
  onClearClusters,
  onNext,
  getClusterColor,
}: Step1Props) {
  const allSelected = shipments.length > 0 && selectedShipmentIds.size === shipments.length;

  return (
    <div className="space-y-4">
      <h2 className="text-sm font-semibold text-white">Step 1: Select Shipments</h2>

      {/* Customer filter + load */}
      <div className="flex gap-2">
        <input
          type="text"
          value={customerId}
          onChange={(e) => onCustomerIdChange(e.target.value)}
          placeholder="Customer ID (optional)"
          className="flex-1 bg-slate-800/80 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-blue-500/50"
        />
        <button
          onClick={onLoadShipments}
          disabled={isLoading}
          className="px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
        >
          {isLoading ? 'Loading...' : 'Load'}
        </button>
      </div>

      {/* Clustering controls */}
      {shipments.length > 0 && (
        <div className="bg-purple-900/30 border border-purple-700/40 rounded-lg p-3 space-y-2">
          <div className="flex items-center gap-2">
            <label className="text-xs font-medium text-purple-300">Zones</label>
            <input
              type="number"
              value={numClusters}
              onChange={(e) => onNumClustersChange(Math.max(2, Math.min(10, Number(e.target.value))))}
              min={2}
              max={10}
              className="w-14 bg-slate-800 border border-purple-700/50 rounded px-2 py-1 text-sm text-white focus:outline-none"
            />
            <button
              onClick={onCluster}
              disabled={isClustering || selectedShipmentIds.size < 2}
              className="px-3 py-1 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white text-xs font-medium rounded transition-colors"
            >
              {isClustering ? 'Clustering...' : 'Cluster'}
            </button>
            {clusters.length > 0 && (
              <button
                onClick={onClearClusters}
                className="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs font-medium rounded transition-colors"
              >
                Clear
              </button>
            )}
          </div>

          {/* Cluster summary pills */}
          {clusters.length > 0 && (
            <div className="flex flex-wrap gap-1.5">
              {clusters.map((cluster, idx) => (
                <span
                  key={idx}
                  className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                  style={{ backgroundColor: cluster.color || ROUTE_COLORS[idx % ROUTE_COLORS.length] }}
                >
                  Zone {idx + 1}: {cluster.shipmentIds.length}
                </span>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Shipment list */}
      {shipments.length > 0 && (
        <>
          <div className="flex items-center justify-between">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={allSelected}
                onChange={(e) => onToggleAll(e.target.checked)}
                className="rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-blue-500/30"
              />
              <span className="text-xs font-medium text-slate-300">Select all</span>
            </label>
            <span className="text-xs text-slate-500">
              {selectedShipmentIds.size} / {shipments.length}
            </span>
          </div>

          <div className="max-h-[calc(100vh-420px)] overflow-y-auto rounded-lg border border-slate-700/50 divide-y divide-slate-700/30">
            {shipments.map((shipment) => {
              const clusterColor = getClusterColor(shipment.publicId);
              return (
                <div
                  key={shipment.publicId}
                  className="flex items-center gap-3 px-3 py-2.5 hover:bg-slate-800/60 transition-colors cursor-pointer"
                  onClick={() => onToggleShipment(shipment.publicId)}
                >
                  <input
                    type="checkbox"
                    checked={selectedShipmentIds.has(shipment.publicId)}
                    onChange={() => onToggleShipment(shipment.publicId)}
                    onClick={(e) => e.stopPropagation()}
                    className="rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-blue-500/30"
                  />
                  {clusterColor && (
                    <div className="w-3 h-3 rounded-full flex-shrink-0" style={{ backgroundColor: clusterColor }} />
                  )}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm text-white truncate">{shipment.recipientName}</p>
                    <p className="text-xs text-slate-400 truncate">{shipment.address}</p>
                  </div>
                  {shipment.totalWeightKg != null && (
                    <span className="text-xs text-slate-500 flex-shrink-0">{shipment.totalWeightKg} kg</span>
                  )}
                  {shipment.addressRisk?.is_risky && (
                    <span className="text-xs bg-red-900/50 text-red-400 px-1.5 py-0.5 rounded-full flex-shrink-0">
                      Risk
                    </span>
                  )}
                </div>
              );
            })}
          </div>
        </>
      )}

      {/* Empty state */}
      {shipments.length === 0 && !isLoading && (
        <div className="text-center py-8">
          <p className="text-sm text-slate-500">Load shipments to begin planning routes.</p>
        </div>
      )}

      {/* Next button */}
      <button
        onClick={onNext}
        disabled={selectedShipmentIds.size === 0}
        className="w-full py-2.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition-colors"
      >
        Next ({selectedShipmentIds.size} shipments)
      </button>
    </div>
  );
}

// ── Step 2 Panel ────────────────────────────────────────────────────

interface Step2Props {
  vehicles: PlannerVehicle[];
  selectedVehicleIds: Set<string>;
  isLoading: boolean;
  locations: PlannerLocation[];
  originPublicId: string;
  maxStopsPerRoute: number;
  isGenerating: boolean;
  onToggleVehicle: (id: string) => void;
  onToggleAll: (checked: boolean) => void;
  onOriginChange: (v: string) => void;
  onMaxStopsChange: (v: number) => void;
  onBack: () => void;
  onGenerate: () => void;
}

function Step2Panel({
  vehicles,
  selectedVehicleIds,
  isLoading,
  locations,
  originPublicId,
  maxStopsPerRoute,
  isGenerating,
  onToggleVehicle,
  onToggleAll,
  onOriginChange,
  onMaxStopsChange,
  onBack,
  onGenerate,
}: Step2Props) {
  const allSelected = vehicles.length > 0 && selectedVehicleIds.size === vehicles.length;

  return (
    <div className="space-y-4">
      <h2 className="text-sm font-semibold text-white">Step 2: Configure</h2>

      {isLoading ? (
        <div className="text-center py-8">
          <p className="text-sm text-slate-500">Loading vehicles...</p>
        </div>
      ) : (
        <>
          {/* Vehicle list */}
          <div className="flex items-center justify-between">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={allSelected}
                onChange={(e) => onToggleAll(e.target.checked)}
                className="rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-blue-500/30"
              />
              <span className="text-xs font-medium text-slate-300">Select all vehicles</span>
            </label>
            <span className="text-xs text-slate-500">
              {selectedVehicleIds.size} / {vehicles.length}
            </span>
          </div>

          <div className="max-h-52 overflow-y-auto rounded-lg border border-slate-700/50 divide-y divide-slate-700/30">
            {vehicles.map((vehicle) => (
              <div
                key={vehicle.publicId}
                className="flex items-center gap-3 px-3 py-2.5 hover:bg-slate-800/60 transition-colors cursor-pointer"
                onClick={() => onToggleVehicle(vehicle.publicId)}
              >
                <input
                  type="checkbox"
                  checked={selectedVehicleIds.has(vehicle.publicId)}
                  onChange={() => onToggleVehicle(vehicle.publicId)}
                  onClick={(e) => e.stopPropagation()}
                  className="rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-blue-500/30"
                />
                <div className="flex-1 min-w-0">
                  <p className="text-sm text-white">{vehicle.name}</p>
                  <p className="text-xs text-slate-400">
                    {vehicle.maxWeightKg != null ? `${vehicle.maxWeightKg} kg` : '-'}
                    {' / '}
                    {vehicle.maxVolumeM3 != null ? `${vehicle.maxVolumeM3} m\u00b3` : '-'}
                    {' / '}
                    {vehicle.maxParcels != null ? `${vehicle.maxParcels} parcels` : '-'}
                  </p>
                </div>
              </div>
            ))}
          </div>

          {/* Origin + Max stops */}
          <div className="space-y-3">
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Origin location</label>
              <select
                value={originPublicId}
                onChange={(e) => onOriginChange(e.target.value)}
                className="w-full bg-slate-800/80 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-blue-500/50"
              >
                <option value="">-- No origin --</option>
                {locations.map((loc) => (
                  <option key={loc.publicId} value={loc.publicId}>
                    {loc.name}{loc.isDefault ? ' (default)' : ''}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Max stops per route</label>
              <input
                type="number"
                value={maxStopsPerRoute}
                onChange={(e) => onMaxStopsChange(Math.max(1, Math.min(100, Number(e.target.value))))}
                min={1}
                max={100}
                className="w-full bg-slate-800/80 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500/50"
              />
            </div>
          </div>
        </>
      )}

      {/* Nav buttons */}
      <div className="flex gap-2">
        <button
          onClick={onBack}
          className="flex-1 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-medium rounded-lg transition-colors"
        >
          Back
        </button>
        <button
          onClick={onGenerate}
          disabled={selectedVehicleIds.size === 0 || isGenerating}
          className="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors"
        >
          {isGenerating ? 'Generating...' : `Generate (${selectedVehicleIds.size} vehicles)`}
        </button>
      </div>
    </div>
  );
}

// ── Step 3 Panel ────────────────────────────────────────────────────

interface Step3Props {
  routes: PlannerPreviewRoute[];
  driverSuggestions: Record<string, DriverSuggestion[]>;
  driverAssignments: Record<string, string>;
  loadingDrivers: boolean;
  isConfirming: boolean;
  onDriverAssign: (routeId: string, driverId: string) => void;
  onBack: () => void;
  onCancel: () => void;
  onConfirm: () => void;
}

function Step3Panel({
  routes,
  driverSuggestions,
  driverAssignments,
  loadingDrivers,
  isConfirming,
  onDriverAssign,
  onBack,
  onCancel,
  onConfirm,
}: Step3Props) {
  return (
    <div className="space-y-4">
      <h2 className="text-sm font-semibold text-white">Step 3: Preview & Assign Drivers</h2>
      <p className="text-xs text-slate-400">
        Review routes and assign drivers. The system suggests the best driver based on zone, rating, availability.
      </p>

      {loadingDrivers && (
        <div className="flex items-center justify-center gap-2 py-6">
          <div className="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
          <span className="text-sm text-slate-400">Loading driver suggestions...</span>
        </div>
      )}

      {!loadingDrivers && routes.length === 0 && (
        <div className="text-center py-8">
          <p className="text-sm text-slate-500">No routes generated. Go back to configure.</p>
        </div>
      )}

      {!loadingDrivers && (
        <div className="space-y-3">
          {routes.map((routeData, idx) => {
            const routeId = routeData.route.publicId;
            const suggestions = driverSuggestions[routeId] ?? [];
            const selectedDriverId = driverAssignments[routeId] ?? '';
            const color = ROUTE_COLORS[idx % ROUTE_COLORS.length];

            return (
              <RouteCard
                key={routeId}
                routeData={routeData}
                color={color}
                suggestions={suggestions}
                selectedDriverId={selectedDriverId}
                onDriverAssign={(driverId) => onDriverAssign(routeId, driverId)}
              />
            );
          })}
        </div>
      )}

      {/* Nav buttons */}
      <div className="flex gap-2">
        <button
          onClick={onBack}
          className="py-2.5 px-4 bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-medium rounded-lg transition-colors"
        >
          Back
        </button>
        <button
          onClick={onCancel}
          className="py-2.5 px-4 bg-red-900/50 hover:bg-red-900/70 text-red-300 text-sm font-medium rounded-lg transition-colors"
        >
          Cancel
        </button>
        <button
          onClick={onConfirm}
          disabled={isConfirming || routes.length === 0}
          className="flex-1 py-2.5 bg-green-600 hover:bg-green-500 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors"
        >
          {isConfirming ? 'Confirming...' : 'Confirm Routes'}
        </button>
      </div>
    </div>
  );
}

// ── Route Card ──────────────────────────────────────────────────────

interface RouteCardProps {
  routeData: PlannerPreviewRoute;
  color: string;
  suggestions: DriverSuggestion[];
  selectedDriverId: string;
  onDriverAssign: (driverId: string) => void;
}

function RouteCard({ routeData, color, suggestions, selectedDriverId, onDriverAssign }: RouteCardProps) {
  const validation = routeData.validation;
  const selectedDriver = suggestions.find((s) => s.driver_public_id === selectedDriverId);

  return (
    <div className="rounded-lg border border-slate-700/50 bg-slate-800/60 p-4 space-y-3">
      {/* Route header */}
      <div className="flex items-center gap-2">
        <div className="w-3 h-3 rounded-full flex-shrink-0" style={{ backgroundColor: color }} />
        <div className="flex-1 min-w-0">
          <h3 className="text-sm font-semibold text-white">{routeData.route.name}</h3>
          <p className="text-xs text-slate-400">
            {routeData.stopsCount} stops
            {routeData.route.totalDistanceKm != null && (
              <> &middot; {routeData.route.totalDistanceKm.toFixed(1)} km</>
            )}
            {routeData.route.estimatedDurationMinutes != null && (
              <> &middot; {routeData.route.estimatedDurationMinutes} min</>
            )}
            {routeData.route.vehicle && (
              <> &middot; {routeData.route.vehicle}</>
            )}
          </p>
        </div>
      </div>

      {/* Capacity bars */}
      {validation && (
        <div className="grid grid-cols-3 gap-2">
          <CapacityBar
            label="Weight"
            current={validation.totalWeightKg}
            max={validation.maxWeightKg}
            utilization={validation.weightUtilization}
            unit="kg"
          />
          <CapacityBar
            label="Volume"
            current={validation.totalVolumeM3}
            max={validation.maxVolumeM3}
            utilization={validation.volumeUtilization}
            unit="m\u00b3"
          />
          <CapacityBar
            label="Parcels"
            current={validation.totalParcels}
            max={validation.maxParcels}
            utilization={validation.parcelUtilization}
            unit=""
          />
        </div>
      )}

      {/* Capacity warnings */}
      {validation && !validation.valid && (
        <div className="bg-red-900/30 border border-red-700/40 rounded-lg p-2.5">
          <p className="text-xs font-medium text-red-400 mb-1">Capacity exceeded</p>
          <ul className="list-disc list-inside text-xs text-red-300/80 space-y-0.5">
            {validation.errors.map((err, i) => (
              <li key={i}>{err}</li>
            ))}
          </ul>
        </div>
      )}

      {/* Driver assignment */}
      <div>
        {suggestions.length > 0 && suggestions[0] && (
          <div className="inline-flex items-center gap-1.5 rounded-full bg-green-900/40 border border-green-700/40 px-2.5 py-1 mb-2">
            <svg className="w-3.5 h-3.5 text-green-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span className="text-xs font-medium text-green-300">{suggestions[0].driver_name}</span>
            <span className="text-xs text-green-400/70">{suggestions[0].score} pts</span>
            <span className="text-xs bg-blue-900/50 text-blue-300 px-1.5 py-0.5 rounded-full">
              {suggestions[0].top_criterion}
            </span>
          </div>
        )}

        <select
          value={selectedDriverId}
          onChange={(e) => onDriverAssign(e.target.value)}
          className="w-full bg-slate-800/80 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-blue-500/50"
        >
          <option value="">-- Select driver --</option>
          {suggestions.map((s) => (
            <option key={s.driver_public_id} value={s.driver_public_id}>
              {s.driver_name} ({s.score} pts - {s.top_criterion})
            </option>
          ))}
        </select>

        {/* Score breakdown */}
        {selectedDriver && (
          <div className="grid grid-cols-4 gap-1.5 mt-2">
            {(['zone', 'rating', 'workload', 'skills'] as const).map((key) => (
              <div key={key} className="text-center bg-slate-800/80 rounded p-1.5">
                <div className="text-[10px] text-slate-500 capitalize">{key}</div>
                <div className="text-xs font-semibold text-slate-200">{selectedDriver.breakdown[key]}</div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

// ── Capacity Bar ────────────────────────────────────────────────────

function CapacityBar({
  label,
  current,
  max,
  utilization,
  unit,
}: {
  label: string;
  current?: number;
  max?: number;
  utilization?: number;
  unit: string;
}) {
  const pct = utilization ?? 0;
  const barColor = pct > 90 ? 'bg-red-500' : pct > 70 ? 'bg-yellow-500' : 'bg-green-500';

  return (
    <div>
      <div className="flex items-center justify-between mb-0.5">
        <span className="text-[10px] font-medium text-slate-400">{label}</span>
        <span className="text-[10px] text-slate-500">
          {current?.toFixed(label === 'Volume' ? 2 : 0) ?? '0'} / {max ?? '?'} {unit}
        </span>
      </div>
      <div className="w-full bg-slate-700 rounded-full h-1.5">
        <div
          className={`h-1.5 rounded-full transition-all ${barColor}`}
          style={{ width: `${Math.min(100, pct)}%` }}
        />
      </div>
      <div className="text-right text-[10px] text-slate-500 mt-0.5">{pct.toFixed(0)}%</div>
    </div>
  );
}
