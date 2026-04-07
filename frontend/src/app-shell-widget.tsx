import { useState, StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider } from './context/ThemeProvider';
import { NavigationSidebar } from './components/layout/NavigationSidebar';
import { TopBar } from './components/layout/TopBar';
import './index.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
      staleTime: 5 * 60 * 1000,
    },
  },
});

/**
 * AppShellWidget provides the same TopBar + NavigationSidebar chrome
 * as the React SPA's AppLayout, but for server-rendered Twig pages.
 *
 * It does NOT wrap the Twig content — it only renders the TopBar at the
 * top of #react-shell-root. The Twig content sits below in the DOM.
 */
function AppShellWidget() {
  const [navOpen, setNavOpen] = useState(false);

  return (
    <>
      {navOpen && (
        <NavigationSidebar mode="overlay" onClose={() => setNavOpen(false)} />
      )}
      <TopBar
        compact
        onMenuClick={() => setNavOpen(true)}
      />
    </>
  );
}

const container = document.getElementById('react-shell-root');
if (container) {
  createRoot(container).render(
    <StrictMode>
      <ThemeProvider>
        <QueryClientProvider client={queryClient}>
          <AppShellWidget />
        </QueryClientProvider>
      </ThemeProvider>
    </StrictMode>,
  );
}
