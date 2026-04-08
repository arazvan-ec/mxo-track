import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { RadialGauge } from './RadialGauge';

describe('RadialGauge', () => {
  it('renders value and unit in center', () => {
    render(<RadialGauge value={45} max={200} unit="ms" />);
    expect(screen.getByText('45')).toBeInTheDocument();
    expect(screen.getByText('ms')).toBeInTheDocument();
  });

  it('renders SVG circles (track + fill)', () => {
    const { container } = render(<RadialGauge value={100} max={200} />);
    const circles = container.querySelectorAll('circle');
    expect(circles.length).toBe(2);
  });

  it('uses success color when below thresholds', () => {
    const { container } = render(
      <RadialGauge value={30} max={200} warnThreshold={100} critThreshold={160} />,
    );
    const fill = container.querySelectorAll('circle')[1];
    expect(fill).toHaveAttribute('stroke', 'var(--color-success)');
  });

  it('uses warning color when above warn threshold', () => {
    const { container } = render(
      <RadialGauge value={120} max={200} warnThreshold={100} critThreshold={160} />,
    );
    const fill = container.querySelectorAll('circle')[1];
    expect(fill).toHaveAttribute('stroke', 'var(--color-warning)');
  });

  it('uses error color when above crit threshold', () => {
    const { container } = render(
      <RadialGauge value={180} max={200} warnThreshold={100} critThreshold={160} />,
    );
    const fill = container.querySelectorAll('circle')[1];
    expect(fill).toHaveAttribute('stroke', 'var(--color-error)');
  });

  it('renders custom label instead of unit', () => {
    render(<RadialGauge value={50} max={100} unit="" label="CPU" />);
    expect(screen.getByText('CPU')).toBeInTheDocument();
  });
});
