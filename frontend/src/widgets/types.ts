export interface WidgetProps {
  data: unknown;
  expanded?: boolean;
}

export interface WidgetRegistryMeta {
  /** If true, widget is wrapped in CollapsibleWidget when rendered in page mode */
  collapsible?: boolean;
  /** Section title shown in the CollapsibleWidget header */
  sectionTitle?: string;
}
