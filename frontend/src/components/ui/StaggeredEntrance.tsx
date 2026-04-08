import { Children, type ReactNode } from 'react';

interface StaggeredEntranceProps {
  children: ReactNode;
  staggerMs?: number;
  className?: string;
}

export function StaggeredEntrance({ children, staggerMs = 60, className }: StaggeredEntranceProps) {
  return (
    <div className={className}>
      {Children.map(children, (child, index) => (
        <div
          className="animate-fade-in-up"
          style={{ animationDelay: `${index * staggerMs}ms` }}
        >
          {child}
        </div>
      ))}
    </div>
  );
}
