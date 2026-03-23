import type { LayoutConfig, SheetStateName, WidgetType } from '@/types/layout';
import { WIDGET_REGISTRY } from '@/widgets/registry';
import { PlaceholderWidget } from '@/widgets/PlaceholderWidget';

interface WidgetRendererProps {
  layout: LayoutConfig;
  sheetState: SheetStateName;
  pageData: unknown;
}

export function WidgetRenderer({ layout, sheetState, pageData }: WidgetRendererProps) {
  const widgets = layout.widgets[sheetState] ?? [];

  if (widgets.length === 0) return null;

  const expanded = sheetState !== 'collapsed';

  return (
    <>
      {widgets.map(({ type, position }) => {
        const entry = WIDGET_REGISTRY[type];
        if (entry) {
          const Component = entry.component;
          return <Component key={`${type}-${position}`} data={pageData} expanded={expanded} />;
        }
        // Unimplemented widget type — show placeholder
        return <PlaceholderWidget key={`${type}-${position}`} widgetType={type as WidgetType} />;
      })}
    </>
  );
}
