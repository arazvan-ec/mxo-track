import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/api/client';
import { WIDGET_REGISTRY, ALL_WIDGET_TYPES } from '@/widgets/registry';


interface WidgetDefinitionDto {
  publicId: string;
  type: string;
  label: string;
  description: string | null;
  previewImage: string | null;
  active: boolean;
}

export function WidgetGalleryPage() {
  const queryClient = useQueryClient();

  const { data: widgets, isLoading } = useQuery({
    queryKey: ['admin-widgets'],
    queryFn: () => api.get<WidgetDefinitionDto[]>('/api/admin/widgets'),
  });

  const toggleMutation = useMutation({
    mutationFn: ({ publicId, active }: { publicId: string; active: boolean }) =>
      api.post(`/api/admin/widgets/${publicId}`, { active }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-widgets'] }),
  });

  // Merge API data with registry info
  const widgetCards = ALL_WIDGET_TYPES.map((wt) => {
    const apiWidget = widgets?.find((w) => w.type === wt.type);
    return {
      ...wt,
      publicId: apiWidget?.publicId ?? null,
      active: apiWidget?.active ?? true,
      description: apiWidget?.description ?? wt.description,
      hasComponent: !!WIDGET_REGISTRY[wt.type],
    };
  });

  return (
    <div className="flex-1 overflow-y-auto p-6 bg-slate-900">
        <div className="max-w-5xl mx-auto">
          <h1 className="text-xl font-bold text-slate-100 mb-1">Widget Gallery</h1>
          <p className="text-sm text-slate-400 mb-6">
            All available widget types for bottom sheet pages
          </p>

          {isLoading ? (
            <div className="text-slate-400 text-sm">Loading widgets...</div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {widgetCards.map((card) => (
                <div
                  key={card.type}
                  className={`bg-slate-800 rounded-lg border overflow-hidden transition-all ${
                    card.active ? 'border-slate-700' : 'border-slate-700/50 opacity-60'
                  }`}
                >
                  {/* Preview area */}
                  <div className="bg-slate-900/50 p-4 min-h-[120px] flex items-center justify-center">
                    {card.hasComponent ? (
                      <span className="text-xs text-emerald-400 bg-emerald-400/10 px-2 py-1 rounded">
                        Implemented
                      </span>
                    ) : (
                      <span className="text-xs text-slate-500 bg-slate-700/50 px-2 py-1 rounded">
                        Coming soon
                      </span>
                    )}
                  </div>

                  {/* Info */}
                  <div className="p-3 border-t border-slate-700">
                    <div className="flex items-center justify-between mb-1">
                      <h3 className="text-sm font-semibold text-slate-200">{card.label}</h3>
                      {card.publicId && (
                        <button
                          type="button"
                          className={`text-xs px-2 py-0.5 rounded transition-colors ${
                            card.active
                              ? 'bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30'
                              : 'bg-slate-700 text-slate-400 hover:bg-slate-600'
                          }`}
                          onClick={() =>
                            toggleMutation.mutate({
                              publicId: card.publicId!,
                              active: !card.active,
                            })
                          }
                        >
                          {card.active ? 'Active' : 'Inactive'}
                        </button>
                      )}
                    </div>
                    <p className="text-xs text-slate-400">{card.description}</p>
                    <p className="text-[10px] text-slate-500 mt-1 font-mono">{card.type}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
    </div>
  );
}
