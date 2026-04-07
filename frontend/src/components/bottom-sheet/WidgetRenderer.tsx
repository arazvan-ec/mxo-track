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
        const rendered = <Component key={`${type}-${position}`} data={pageData} expanded={expanded} />;

        if (mode === 'page' && entry.collapsible && entry.sectionTitle) {
          return (
            <CollapsibleWidget
              key={`${type}-${position}`}
              title={entry.sectionTitle}
              storageKey={`mxo-dashboard-widget-${type}-minimized`}
            >
              <Component data={pageData} expanded={expanded} />
            </CollapsibleWidget>
          );
        }

        return rendered;
      })}
    </>
  );
}
