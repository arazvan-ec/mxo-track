export interface WidgetProps {
  data: unknown;
  expanded?: boolean;
}

export interface WidgetRegistryMeta {
  /** If true, widget is wrapped in CollapsibleWidget when rendered in page mode */
  collapsible?: boolean;
  /** Section title shown in the CollapsibleWidget header */
  sectionTitle?: string;
  /** If true, CollapsibleWidget exposes a detail toggle passed as `expanded` to the widget */
  supportsDetail?: boolean;
  /** Default state for the detail toggle (default: false) */
  defaultDetailed?: boolean;
  /** If true, widget starts hidden/minimized (default: false) */
  defaultMinimized?: boolean;
}
