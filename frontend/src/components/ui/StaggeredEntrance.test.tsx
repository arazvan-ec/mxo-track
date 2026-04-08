import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { StaggeredEntrance } from './StaggeredEntrance';

describe('StaggeredEntrance', () => {
  it('renders all children', () => {
    const { container } = render(
      <StaggeredEntrance>
        <div>A</div>
        <div>B</div>
        <div>C</div>
      </StaggeredEntrance>,
    );
    expect(container.textContent).toContain('A');
    expect(container.textContent).toContain('B');
    expect(container.textContent).toContain('C');
  });

  it('applies incremental animation delay to each child', () => {
    const { container } = render(
      <StaggeredEntrance staggerMs={100}>
        <div>First</div>
        <div>Second</div>
        <div>Third</div>
      </StaggeredEntrance>,
    );
    const wrappers = container.querySelectorAll('.animate-fade-in-up');
    expect(wrappers.length).toBe(3);
    expect(wrappers[0]).toHaveStyle({ animationDelay: '0ms' });
    expect(wrappers[1]).toHaveStyle({ animationDelay: '100ms' });
    expect(wrappers[2]).toHaveStyle({ animationDelay: '200ms' });
  });

  it('uses default 60ms stagger when not specified', () => {
    const { container } = render(
      <StaggeredEntrance>
        <div>A</div>
        <div>B</div>
      </StaggeredEntrance>,
    );
    const wrappers = container.querySelectorAll('.animate-fade-in-up');
    expect(wrappers[0]).toHaveStyle({ animationDelay: '0ms' });
    expect(wrappers[1]).toHaveStyle({ animationDelay: '60ms' });
  });

  it('passes className to wrapper div', () => {
    const { container } = render(
      <StaggeredEntrance className="custom-class">
        <div>A</div>
      </StaggeredEntrance>,
    );
    expect(container.querySelector('.custom-class')).toBeInTheDocument();
  });
});
