import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { CollapsibleWidget } from './CollapsibleWidget';

describe('CollapsibleWidget', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('renders title and chevron toggle', () => {
    render(
      <CollapsibleWidget title="Test Widget" storageKey="test-key">
        <p>content</p>
      </CollapsibleWidget>,
    );
    expect(screen.getByText('Test Widget')).toBeInTheDocument();
    // Chevron button with aria-label
    expect(screen.getByLabelText('Toggle expand')).toBeInTheDocument();
  });

  it('does not render detail button when supportsDetail is undefined', () => {
    render(
      <CollapsibleWidget title="No Detail" storageKey="no-detail">
        <p>content</p>
      </CollapsibleWidget>,
    );
    expect(screen.queryByLabelText('Toggle detail')).not.toBeInTheDocument();
  });

  it('renders detail toggle button when supportsDetail=true', () => {
    render(
      <CollapsibleWidget title="With Detail" storageKey="with-detail" supportsDetail>
        <p>content</p>
      </CollapsibleWidget>,
    );
    expect(screen.getByLabelText('Toggle detail')).toBeInTheDocument();
  });

  it('clicking minimize toggles content visibility and persists to localStorage with -minimized suffix', () => {
    // storageKey used directly as localStorage key (callers already add -minimized)
    render(
      <CollapsibleWidget title="Min" storageKey="widget-minimized" defaultExpanded>
        <p>body</p>
      </CollapsibleWidget>,
    );
    const chevron = screen.getByLabelText('Toggle expand');
    // Initially expanded → stored as 'true' is default, click collapses
    fireEvent.click(chevron);
    expect(localStorage.getItem('widget-minimized')).toBe('false');
    // Click again to expand
    fireEvent.click(chevron);
    expect(localStorage.getItem('widget-minimized')).toBe('true');
  });

  it('clicking detail toggle persists to localStorage with -detailed suffix', () => {
    render(
      <CollapsibleWidget title="Detail" storageKey="widget-key" supportsDetail>
        <p>content</p>
      </CollapsibleWidget>,
    );
    const detailBtn = screen.getByLabelText('Toggle detail');
    fireEvent.click(detailBtn);
    expect(localStorage.getItem('widget-key-detailed')).toBe('true');
    fireEvent.click(detailBtn);
    expect(localStorage.getItem('widget-key-detailed')).toBe('false');
  });

  it('children render-prop receives current detailed state', () => {
    render(
      <CollapsibleWidget title="RenderProp" storageKey="rp-key" supportsDetail defaultDetailed={false}>
        {(detailed) => <span data-testid="detail-state">{String(detailed)}</span>}
      </CollapsibleWidget>,
    );
    expect(screen.getByTestId('detail-state').textContent).toBe('false');
    const detailBtn = screen.getByLabelText('Toggle detail');
    fireEvent.click(detailBtn);
    expect(screen.getByTestId('detail-state').textContent).toBe('true');
  });

  it('defaultDetailed=true initializes detailed mode if no storage value', () => {
    render(
      <CollapsibleWidget title="Default" storageKey="dd-key" supportsDetail defaultDetailed>
        {(detailed) => <span data-testid="det">{String(detailed)}</span>}
      </CollapsibleWidget>,
    );
    expect(screen.getByTestId('det').textContent).toBe('true');
  });

  it('localStorage value overrides defaultDetailed', () => {
    localStorage.setItem('ls-key-detailed', 'true');
    render(
      <CollapsibleWidget title="Override" storageKey="ls-key" supportsDetail defaultDetailed={false}>
        {(detailed) => <span data-testid="det">{String(detailed)}</span>}
      </CollapsibleWidget>,
    );
    expect(screen.getByTestId('det').textContent).toBe('true');
  });

  it('onDetailChange callback fires when detail toggles', () => {
    const cb = vi.fn();
    render(
      <CollapsibleWidget title="CB" storageKey="cb-key" supportsDetail onDetailChange={cb}>
        <p>content</p>
      </CollapsibleWidget>,
    );
    const detailBtn = screen.getByLabelText('Toggle detail');
    fireEvent.click(detailBtn);
    expect(cb).toHaveBeenCalledWith(true);
    fireEvent.click(detailBtn);
    expect(cb).toHaveBeenCalledWith(false);
  });

  it('chevron has rotation transition with var(--ease-ios, ...) in style attribute', () => {
    const { container } = render(
      <CollapsibleWidget title="iOS" storageKey="ios-key">
        <p>content</p>
      </CollapsibleWidget>,
    );
    const chevron = container.querySelector('svg');
    expect(chevron).toBeTruthy();
    const style = chevron!.getAttribute('style') ?? '';
    expect(style).toContain('var(--ease-ios');
    expect(style).toContain('var(--dur-fast');
  });
});
