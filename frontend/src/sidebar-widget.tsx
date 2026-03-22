import { useState, useEffect, StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { NavigationSidebar } from './components/layout/NavigationSidebar';
import './sidebar-widget.css';

declare global {
  interface Window {
    __mxoSidebarOpen?: () => void;
  }
}

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
      staleTime: 5 * 60 * 1000,
    },
  },
});

function SidebarWidget() {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    window.__mxoSidebarOpen = () => setOpen(true);
    return () => {
      delete window.__mxoSidebarOpen;
    };
  }, []);

  if (!open) return null;

  return (
    <NavigationSidebar mode="overlay" onClose={() => setOpen(false)} />
  );
}

const container = document.getElementById('react-sidebar-root');
if (container) {
  createRoot(container).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <SidebarWidget />
      </QueryClientProvider>
    </StrictMode>,
  );
}
