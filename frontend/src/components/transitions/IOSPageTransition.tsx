import { type ReactNode } from 'react';
import { useLocation } from 'react-router';
import { useTheme } from '@/context/ThemeProvider';

interface IOSPageTransitionProps {
  children: ReactNode;
}

/**
 * Wraps page content and applies an iOS-style push-in animation when the
 * `ios` preset is active. The animation re-triggers on `location.key` change
 * by remounting via React's `key` prop.
 *
 * For non-iOS presets this wrapper renders a passthrough `<div>` with the
 * same layout contract (`h-full w-full`) so pages behave identically.
 */
export function IOSPageTransition({ children }: IOSPageTransitionProps) {
  const location = useLocation();
  const { preset } = useTheme();

  const className =
    preset === 'ios'
      ? 'h-full w-full animate-ios-push-in'
      : 'h-full w-full';

  return (
    <div key={location.key} className={className}>
      {children}
    </div>
  );
}
