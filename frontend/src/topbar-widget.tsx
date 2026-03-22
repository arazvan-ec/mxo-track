import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
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

function TopBarWidget() {
  return (
    <TopBar
      compact={false}
      onMenuClick={() => window.__mxoSidebarOpen?.()}
    />
  );
}

const container = document.getElementById('react-topbar-root');
if (container) {
  createRoot(container).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <TopBarWidget />
      </QueryClientProvider>
    </StrictMode>,
  );
}
