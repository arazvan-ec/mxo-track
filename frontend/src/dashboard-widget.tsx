import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AdminDashboardPage } from './pages/admin/AdminDashboardPage';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
      staleTime: 10 * 1000,
    },
  },
});

const container = document.getElementById('mxo-dashboard-root');
if (container) {
  // Read Mercure URL from the data attribute set by base.html.twig
  const mercureEl = document.querySelector('[data-mercure-url]');
  const mercurePublicUrl = mercureEl?.getAttribute('data-mercure-url') ?? undefined;

  createRoot(container).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <AdminDashboardPage mercurePublicUrl={mercurePublicUrl} />
      </QueryClientProvider>
    </StrictMode>,
  );
}
