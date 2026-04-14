import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider } from './context/ThemeProvider';
import { UserPreferencesProvider } from './context/UserPreferencesContext';
import { router } from './router';
import './index.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ThemeProvider>
      <QueryClientProvider client={queryClient}>
        <UserPreferencesProvider>
          <RouterProvider router={router} />
        </UserPreferencesProvider>
      </QueryClientProvider>
    </ThemeProvider>
  </StrictMode>,
);
