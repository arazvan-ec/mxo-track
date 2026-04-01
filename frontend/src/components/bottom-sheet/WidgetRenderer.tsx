import type { LayoutConfig, SheetStateName } from '@/types/layout';
import { WIDGET_REGISTRY } from '@/widgets/registry';

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
        if (!entry) return null;
        const Component = entry.component;
        return <Component key={`${type}-${position}`} data={pageData} expanded={expanded} />;
      })}
    </>
  );
}
