import type { WidgetType } from '@/types/layout';

interface PlaceholderWidgetProps {
  widgetType: WidgetType;
}

export function PlaceholderWidget({ widgetType }: PlaceholderWidgetProps) {
  return (
    <div className="px-4 pb-3">
      <div className="border-2 border-dashed border-slate-700 rounded-lg p-4 text-center">
        <p className="text-sm text-slate-400 font-medium">
          {widgetType.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
        </p>
        <p className="text-xs text-slate-500 mt-1">Coming soon</p>
      </div>
    </div>
  );
}
