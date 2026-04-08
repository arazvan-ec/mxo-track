import { render, screen, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { AnimatedCounter } from './AnimatedCounter';

describe('AnimatedCounter', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('renders initial value of 0', () => {
    render(<AnimatedCounter value={100} />);
    // On first frame, starts at 0
    expect(screen.getByText('0')).toBeInTheDocument();
  });

  it('reaches target value after animation completes', () => {
    render(<AnimatedCounter value={42} duration={100} />);
    // Advance past animation duration
    act(() => {
      vi.advanceTimersByTime(200);
    });
    expect(screen.getByText('42')).toBeInTheDocument();
  });

  it('applies custom formatter', () => {
    render(
      <AnimatedCounter value={1000} duration={50} formatter={(n) => `${n} items`} />,
    );
    act(() => {
      vi.advanceTimersByTime(100);
    });
    expect(screen.getByText('1000 items')).toBeInTheDocument();
  });

  it('applies className and style props', () => {
    const { container } = render(
      <AnimatedCounter value={5} className="test-class" style={{ color: 'red' }} />,
    );
    const span = container.querySelector('.test-class');
    expect(span).toBeInTheDocument();
    expect(span).toHaveStyle({ color: 'red' });
  });
});
