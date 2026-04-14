import type { ComponentType } from 'react';

export interface WidgetProps {
  data: unknown;
  expanded?: boolean;
}

export interface WidgetRegistryMeta {
  /** If true, widget is wrapped in CollapsibleWidget when rendered in page mode */
  collapsible?: boolean;
  /** Section title shown in the CollapsibleWidget header */
  sectionTitle?: string;
  /**
   * Optional component rendered in the CollapsibleWidget header next to the title.
   * Visible regardless of expanded state — surface the widget's key datum here so
   * collapsing doesn't hide critical info. See docs/knowledge/ui-frontend.md
   * "Collapsible components UX".
   */
  summaryComponent?: ComponentType<WidgetProps>;
}
