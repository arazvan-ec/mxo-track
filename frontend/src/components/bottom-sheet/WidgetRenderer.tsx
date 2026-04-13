import type { LayoutConfig, SheetStateName } from '@/types/layout';
import { WIDGET_REGISTRY } from '@/widgets/registry';
import { CollapsibleWidget } from '@/components/widgets/CollapsibleWidget';

interface WidgetRendererProps {
  layout: LayoutConfig;
  sheetState: SheetStateName;
  pageData: unknown;
  /** When 'page', collapsible widgets are wrapped in CollapsibleWidget */
  mode?: 'sheet' | 'page';
}

export function WidgetRenderer({ layout, sheetState, pageData, mode = 'sheet' }: WidgetRendererProps) {
  const widgets = layout.widgets[sheetState] ?? [];

  if (widgets.length === 0) return null;

  const expanded = sheetState !== 'collapsed';

  return (
    <>
      {widgets.map(({ type, position }) => {
        const entry = WIDGET_REGISTRY[type];
        if (!entry) return null;
        const Component = entry.component;

        // Page mode: wrap collapsible widgets in CollapsibleWidget
        if (mode === 'page' && entry.collapsible && entry.sectionTitle) {
          const baseKey = `mxo-dashboard-widget-${type}`;

          if (entry.supportsDetail) {
            // Render-prop: CollapsibleWidget passes detailed boolean to children
            return (
              <CollapsibleWidget
                key={`${type}-${position}`}
                title={entry.sectionTitle}
                storageKey={baseKey}
                supportsDetail
                defaultDetailed={entry.defaultDetailed}
                defaultExpanded={entry.defaultMinimized ? false : true}
              >
                {(detailed: boolean) => <Component data={pageData} expanded={detailed} />}
              </CollapsibleWidget>
            );
          }

          // Legacy path (e.g. reports_banner): plain children, no detail toggle
          return (
            <CollapsibleWidget
              key={`${type}-${position}`}
              title={entry.sectionTitle}
              storageKey={baseKey}
              defaultExpanded={entry.defaultMinimized ? false : true}
            >
              <Component data={pageData} expanded={expanded} />
            </CollapsibleWidget>
          );
        }

        // Sheet mode: expanded derives from sheetState as before
        return <Component key={`${type}-${position}`} data={pageData} expanded={expanded} />;
      })}
    </>
  );
}
