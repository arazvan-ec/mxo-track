import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { SparklineSVG } from './SparklineSVG';

describe('SparklineSVG', () => {
  it('renders SVG with correct dimensions', () => {
    const { container } = render(<SparklineSVG data={[1, 2, 3]} width={80} height={24} />);
    const svg = container.querySelector('svg');
    expect(svg).toBeInTheDocument();
    expect(svg).toHaveAttribute('width', '80');
    expect(svg).toHaveAttribute('height', '24');
  });

  it('renders a line path and a fill path', () => {
    const { container } = render(<SparklineSVG data={[10, 20, 30]} />);
    const paths = container.querySelectorAll('path');
    expect(paths.length).toBe(2); // fill + line
  });

  it('renders a dot on the last data point', () => {
    const { container } = render(<SparklineSVG data={[5, 10, 15]} />);
    const circle = container.querySelector('circle');
    expect(circle).toBeInTheDocument();
  });

  it('returns null for fewer than 2 data points', () => {
    const { container } = render(<SparklineSVG data={[5]} />);
    expect(container.querySelector('svg')).toBeNull();
  });

  it('accepts custom color prop', () => {
    const { container } = render(<SparklineSVG data={[1, 2, 3]} color="red" />);
    const linePath = container.querySelectorAll('path')[1];
    expect(linePath).toHaveAttribute('stroke', 'red');
  });
});
