import { useRef, useEffect, useMemo, useState, useCallback } from 'react';
import { useParams } from 'react-router';
import { MapCanvas, type MapCanvasHandle } from '@/components/maps/MapCanvas';
import { RoutePolylineLayer } from '@/components/maps/layers/RoutePolylineLayer';
import { StopMarkersLayer } from '@/components/maps/layers/StopMarkersLayer';
import { StopPopup } from '@/components/maps/shared/StopPopup';
import { BottomSheet, type BottomSheetState } from '@/components/bottom-sheet/BottomSheet';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';
import { SHEET_HEIGHTS } from '@/components/bottom-sheet/useBottomSheet';
import { useRouteAnalysis } from '@/api/hooks/useRouteAnalysis';
import { usePageLayout } from '@/api/hooks/usePageLayout';
import { WidgetRenderer } from '@/components/bottom-sheet/WidgetRenderer';
import type { RouteData } from '@/api/types';

/** Extract comparison stats from route data. */
function getComparisonStats(route: RouteData | null) {
  if (!route) return null;
  const metrics = route.metrics as Record<string, number> | undefined;
  if (!metrics) return null;

  return {
    plannedDistanceKm: metrics.distanceBeforeKm ?? metrics.distanceAfterKm ?? 0,
    actualDistanceKm: metrics.distanceAfterKm ?? 0,
    deviationKm: Math.abs(
      (metrics.distanceBeforeKm ?? 0) - (metrics.distanceAfterKm ?? 0),
    ),
    extraTimeMinutes: (metrics.totalTimeMinutes ?? 0) - (metrics.drivingTimeMinutes ?? 0),
  };
}

export function RouteAnalysisPage() {
  const { publicId } = useParams<{ publicId: string }>();
  const mapRef = useRef<MapCanvasHandle>(null);
  const { route, stops, isLoading, error } = useRouteAnalysis(publicId);
  const [navOpen, setNavOpen] = useState(false);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');

  const [selectedStopSequence, setSelectedStopSequence] = useState<number | null>(null);
  const comparison = useMemo(() => getComparisonStats(route), [route]);
  const { layout } = usePageLayout('route_analysis');

  // Mapped stops for the StopMarkersLayer
  const mappedStops = useMemo(
    () =>
      stops
        .filter((s) => s.lat != null && s.lng != null)
        .map((s) => ({
          lat: s.lat!,
          lng: s.lng!,
          sequence: s.sequence,
          status: s.status,
          address: s.address,
          recipientName: s.recipientName,
          shipmentPublicId: s.shipmentPublicId,
        })),
    [stops],
  );

  const handleStopClick = useCallback(
    (sequence: number) => {
      setSelectedStopSequence((prev) => (prev === sequence ? null : sequence));
      const stop = mappedStops.find((s) => s.sequence === sequence);
      if (stop) {
        const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
        mapRef.current?.flyTo(stop.lng, stop.lat, 16, { bottom: bottomPadding });
      }
    },
    [mappedStops, sheetState],
  );

  // Auto-fit bounds
  useEffect(() => {
    if (mappedStops.length > 0) {
      mapRef.current?.fitBounds(mappedStops);
    }
  }, [mappedStops]);

  const metrics = (route?.metrics ?? undefined) as Record<string, number> | undefined;

  const pageData = useMemo(
    () => ({
      route,
      metrics,
      comparison,
      stops,
      selectedSequence: selectedStopSequence,
      onStopClick: handleStopClick,
      showEta: false,
      showComparison: true,
      vehicleInfo: route
        ? { name: route.vehicleName, driverName: route.driverName }
        : undefined,
    }),
    [route, metrics, comparison, stops, selectedStopSequence, handleStopClick],
  );

  return (
    <div className="flex flex-col h-screen w-full">
      {navOpen && <NavigationSidebar mode="overlay" onClose={() => setNavOpen(false)} />}
      <TopBar compact onMenuClick={() => setNavOpen(true)} />
      <div className="flex-1 relative overflow-hidden">
        <MapCanvas ref={mapRef}>
          {/* Planned route polyline (blue, solid) */}
          {route?.polyline && (
            <RoutePolylineLayer
              id={`analysis-planned-${route.publicId}`}
              polyline={route.polyline}
              color="#3b82f6"
            />
          )}

          {/* Actual route polyline (red, comparison) */}
          {route?.comparisonPolyline && (
            <RoutePolylineLayer
              id={`analysis-actual-${route.publicId}`}
              polyline={route.comparisonPolyline}
              color="#ef4444"
            />
          )}

          {/* Stop markers */}
          <StopMarkersLayer
            stops={mappedStops}
            keyPrefix="analysis-"
            routeColor="#3b82f6"
            onStopClick={handleStopClick}
            selectedSequence={selectedStopSequence}
            renderPopup={(stop) => (
              <StopPopup
                sequence={stop.sequence}
                address={stop.address}
                status={stop.status}
                recipientName={stop.recipientName}
                shipmentPublicId={stop.shipmentPublicId}
              />
            )}
          />
        </MapCanvas>
        <BottomSheet
          state={sheetState}
          onStateChange={setSheetState}
          title={route?.name ? `${route.name} — Analysis` : 'Route Analysis'}
          isLoading={isLoading}
          error={error}
          loadingText="Cargando analisis de ruta..."
        >
          <div className="px-4 pb-4 space-y-4">
            <WidgetRenderer layout={layout} sheetState={sheetState} pageData={pageData} />
          </div>
        </BottomSheet>
      </div>
    </div>
  );
}