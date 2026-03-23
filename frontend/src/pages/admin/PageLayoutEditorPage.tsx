import { useState, useEffect, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/api/client';
import type { WidgetType, SheetStateName, LayoutConfig } from '@/types/layout';
import { ALL_WIDGET_TYPES } from '@/widgets/registry';
import { WidgetRenderer } from '@/components/bottom-sheet/WidgetRenderer';
import { TopBar } from '@/components/layout/TopBar';
import { NavigationSidebar } from '@/components/layout/NavigationSidebar';

const PAGE_OPTIONS: { value: string; label: string }[] = [
  { value: 'test_routing', label: 'Test Routing' },
  { value: 'fleet_map', label: 'Fleet Map' },
  { value: 'route_planner', label: 'Route Planner' },
  { value: 'route_analysis', label: 'Route Analysis' },
  { value: 'route_detail', label: 'Route Detail' },
  { value: 'shipment_tracking', label: 'Shipment Tracking' },
  { value: 'driver_route', label: 'Driver Route' },
  { value: 'customer_tracking', label: 'Customer Tracking' },
];

const SHEET_STATES: { value: SheetStateName; label: string; height: string }[] = [
  { value: 'collapsed', label: 'Collapsed (15%)', height: '120px' },
  { value: 'half', label: 'Half (50%)', height: '200px' },
  { value: 'full', label: 'Full (85%)', height: '320px' },
];

interface LayoutListItem {
  publicId: string;
  pageKey: string;
  customerId: string | null;
  active: boolean;
  widgets?: Record<SheetStateName, { type: WidgetType; position: number }[]>;
}

export function PageLayoutEditorPage() {
  const [navOpen, setNavOpen] = useState(false);
  const [selectedPage, setSelectedPage] = useState('test_routing');
  const [previewState, setPreviewState] = useState<SheetStateName>('half');
  const queryClient = useQueryClient();

  // Local widget state per sheet state
  const [widgetsByState, setWidgetsByState] = useState<Record<SheetStateName, WidgetType[]>>({
    collapsed: [],
    half: [],
    full: [],
  });

  const [layoutPublicId, setLayoutPublicId] = useState<string | null>(null);

  // Fetch layouts for selected page
  const { data: layouts } = useQuery({
    queryKey: ['admin-page-layouts', selectedPage],
    queryFn: () => api.get<LayoutListItem[]>(`/api/admin/page-layouts?pageKey=${selectedPage}`),
  });

  // Load global layout into editor when page changes
  useEffect(() => {
    if (!layouts) return;
    const global = layouts.find((l) => l.customerId === null);
    if (global) {
      setLayoutPublicId(global.publicId);
      // Fetch full layout with widgets
      api.get<LayoutListItem>(`/api/admin/page-layouts/${global.publicId}`).then((full) => {
        if (full.widgets) {
          setWidgetsByState({
            collapsed: (full.widgets.collapsed ?? []).map((w) => w.type),
            half: (full.widgets.half ?? []).map((w) => w.type),
            full: (full.widgets.full ?? []).map((w) => w.type),
          });
        }
      });
    } else {
      setLayoutPublicId(null);
      setWidgetsByState({ collapsed: [], half: [], full: [] });
    }
  }, [layouts, selectedPage]);

  const saveMutation = useMutation({
    mutationFn: (data: { widgets: Record<SheetStateName, { type: string }[]> }) => {
      if (layoutPublicId) {
        return fetch(`/api/admin/page-layouts/${layoutPublicId}`, {
          method: 'PUT',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify(data),
        });
      }
      return fetch('/api/admin/page-layouts', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ pageKey: selectedPage, ...data }),
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-page-layouts'] });
      queryClient.invalidateQueries({ queryKey: ['page-layout'] });
    },
  });

  const handleSave = useCallback(() => {
    const widgets: Record<SheetStateName, { type: string }[]> = {
      collapsed: widgetsByState.collapsed.map((t) => ({ type: t })),
      half: widgetsByState.half.map((t) => ({ type: t })),
      full: widgetsByState.full.map((t) => ({ type: t })),
    };
    saveMutation.mutate({ widgets });
  }, [widgetsByState, saveMutation]);

  const addWidget = (state: SheetStateName, type: WidgetType) => {
    setWidgetsByState((prev) => ({
      ...prev,
      [state]: [...prev[state], type],
    }));
  };

  const removeWidget = (state: SheetStateName, index: number) => {
    setWidgetsByState((prev) => ({
      ...prev,
      [state]: prev[state].filter((_, i) => i !== index),
    }));
  };

  const moveWidget = (state: SheetStateName, index: number, direction: 'up' | 'down') => {
    setWidgetsByState((prev) => {
      const arr = [...prev[state]];
      const newIdx = direction === 'up' ? index - 1 : index + 1;
      if (newIdx < 0 || newIdx >= arr.length) return prev;
      [arr[index], arr[newIdx]] = [arr[newIdx], arr[index]];
      return { ...prev, [state]: arr };
    });
  };

  // Build a layout config for preview
  const previewLayout: LayoutConfig = {
    pageKey: selectedPage as LayoutConfig['pageKey'],
    scope: 'global',
    widgets: {
      collapsed: widgetsByState.collapsed.map((t, i) => ({ type: t, position: i })),
      half: widgetsByState.half.map((t, i) => ({ type: t, position: i })),
      full: widgetsByState.full.map((t, i) => ({ type: t, position: i })),
    },
  };

  return (
    <div className="flex flex-col h-screen w-full bg-slate-900">
      {navOpen && (
        <NavigationSidebar mode="overlay" onClose={() => setNavOpen(false)} />
      )}
      <TopBar compact onMenuClick={() => setNavOpen(true)} />

      <div className="flex-1 overflow-y-auto p-6">
        <div className="max-w-6xl mx-auto">
          <h1 className="text-xl font-bold text-slate-100 mb-1">Page Layout Editor</h1>
          <p className="text-sm text-slate-400 mb-4">
            Configure which widgets appear in each bottom sheet state
          </p>

          {/* Page selector */}
          <div className="flex items-center gap-4 mb-6">
            <label className="text-sm text-slate-300">Page:</label>
            <select
              className="bg-slate-800 border border-slate-600 rounded px-3 py-1.5 text-sm text-slate-200"
              value={selectedPage}
              onChange={(e) => setSelectedPage(e.target.value)}
            >
              {PAGE_OPTIONS.map((p) => (
                <option key={p.value} value={p.value}>
                  {p.label}
                </option>
              ))}
            </select>
            <button
              type="button"
              className="ml-auto bg-blue-600 hover:bg-blue-500 text-white text-sm px-4 py-1.5 rounded transition-colors"
              onClick={handleSave}
              disabled={saveMutation.isPending}
            >
              {saveMutation.isPending ? 'Saving...' : 'Save Layout'}
            </button>
            {saveMutation.isSuccess && (
              <span className="text-xs text-emerald-400">Saved!</span>
            )}
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Left: State columns */}
            <div>
              <h2 className="text-sm font-semibold text-slate-300 mb-3">Widget Placement</h2>

              {/* Available widgets */}
              <div className="mb-4">
                <p className="text-xs text-slate-400 mb-2">Click to add widget to a state:</p>
                <div className="flex flex-wrap gap-1">
                  {ALL_WIDGET_TYPES.map((wt) => (
                    <div key={wt.type} className="relative group">
                      <span className="text-[10px] bg-slate-800 border border-slate-600 text-slate-300 px-2 py-1 rounded cursor-default">
                        {wt.label}
                      </span>
                      {/* Dropdown on hover */}
                      <div className="hidden group-hover:flex absolute top-full left-0 mt-1 bg-slate-700 rounded shadow-lg z-20 flex-col">
                        {SHEET_STATES.map((ss) => (
                          <button
                            key={ss.value}
                            type="button"
                            className="text-[10px] text-slate-200 px-3 py-1 hover:bg-slate-600 whitespace-nowrap text-left"
                            onClick={() => addWidget(ss.value, wt.type)}
                          >
                            + {ss.label}
                          </button>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* State columns */}
              <div className="space-y-4">
                {SHEET_STATES.map((ss) => (
                  <div key={ss.value} className="bg-slate-800 rounded-lg border border-slate-700 p-3">
                    <h3 className="text-xs font-semibold text-slate-400 uppercase mb-2">
                      {ss.label}
                    </h3>
                    {widgetsByState[ss.value].length === 0 ? (
                      <p className="text-xs text-slate-500 italic">No widgets</p>
                    ) : (
                      <div className="space-y-1">
                        {widgetsByState[ss.value].map((type, idx) => {
                          const info = ALL_WIDGET_TYPES.find((w) => w.type === type);
                          return (
                            <div
                              key={`${type}-${idx}`}
                              className="flex items-center gap-2 bg-slate-700/50 rounded px-2 py-1"
                            >
                              <span className="text-[10px] text-slate-500 w-4">{idx}</span>
                              <span className="text-xs text-slate-200 flex-1">
                                {info?.label ?? type}
                              </span>
                              <button
                                type="button"
                                className="text-slate-500 hover:text-slate-300 text-xs"
                                onClick={() => moveWidget(ss.value, idx, 'up')}
                                disabled={idx === 0}
                              >
                                ↑
                              </button>
                              <button
                                type="button"
                                className="text-slate-500 hover:text-slate-300 text-xs"
                                onClick={() => moveWidget(ss.value, idx, 'down')}
                                disabled={idx === widgetsByState[ss.value].length - 1}
                              >
                                ↓
                              </button>
                              <button
                                type="button"
                                className="text-red-500/60 hover:text-red-400 text-xs"
                                onClick={() => removeWidget(ss.value, idx)}
                              >
                                ✕
                              </button>
                            </div>
                          );
                        })}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {/* Right: Live preview */}
            <div>
              <h2 className="text-sm font-semibold text-slate-300 mb-3">Live Preview</h2>

              {/* State tabs */}
              <div className="flex gap-1 mb-3">
                {SHEET_STATES.map((ss) => (
                  <button
                    key={ss.value}
                    type="button"
                    className={`text-xs px-3 py-1 rounded transition-colors ${
                      previewState === ss.value
                        ? 'bg-blue-600 text-white'
                        : 'bg-slate-800 text-slate-400 hover:text-slate-200'
                    }`}
                    onClick={() => setPreviewState(ss.value)}
                  >
                    {ss.label}
                  </button>
                ))}
              </div>

              {/* Preview container */}
              <div className="bg-slate-800 rounded-lg border border-slate-700 overflow-hidden">
                <div className="py-2 px-4 border-b border-slate-700">
                  <div className="w-10 h-1 bg-slate-600 rounded-full mx-auto mb-1" />
                  <p className="text-xs font-semibold text-slate-200 text-center">
                    {PAGE_OPTIONS.find((p) => p.value === selectedPage)?.label} Results
                  </p>
                </div>
                <div
                  className="overflow-y-auto"
                  style={{
                    maxHeight: SHEET_STATES.find((s) => s.value === previewState)?.height ?? '200px',
                  }}
                >
                  {widgetsByState[previewState].length === 0 ? (
                    <p className="text-xs text-slate-500 italic p-4 text-center">
                      No widgets in this state
                    </p>
                  ) : (
                    <WidgetRenderer
                      layout={previewLayout}
                      sheetState={previewState}
                      pageData={{}}
                    />
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
