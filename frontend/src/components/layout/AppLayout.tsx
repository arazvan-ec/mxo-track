import { useState, createContext, useContext, type ReactNode } from 'react';
import { Outlet } from 'react-router';
import { NavigationSidebar } from './NavigationSidebar';
import { TopBar } from './TopBar';
import { IOSPageTransition } from '../transitions/IOSPageTransition';

/* ── Context ──────────────────────────────────────────────────────── */

interface AppLayoutContextValue {
  setExtraControls: (node: ReactNode) => void;
}

const AppLayoutContext = createContext<AppLayoutContextValue | null>(null);

export function useAppLayout(): AppLayoutContextValue {
  const ctx = useContext(AppLayoutContext);
  if (!ctx) throw new Error('useAppLayout must be used within AppLayout');
  return ctx;
}

/* ── Layout ───────────────────────────────────────────────────────── */

export function AppLayout() {
  const [navOpen, setNavOpen] = useState(false);
  const [extraControls, setExtraControls] = useState<ReactNode>(null);

  return (
    <AppLayoutContext.Provider value={{ setExtraControls }}>
      <div className="flex flex-col h-screen w-full">
        {navOpen && (
          <NavigationSidebar mode="overlay" onClose={() => setNavOpen(false)} />
        )}
        <TopBar
          compact
          onMenuClick={() => setNavOpen(true)}
          extraControls={extraControls}
        />
        <div className="flex-1 relative overflow-hidden">
          <IOSPageTransition>
            <Outlet />
          </IOSPageTransition>
        </div>
      </div>
    </AppLayoutContext.Provider>
  );
}
