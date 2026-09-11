import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClientProvider } from '@tanstack/react-query';
import { registerSW } from 'virtual:pwa-register';
import './index.css';
import './lib/i18n';
import { App } from './App.tsx';
import { AuthProvider } from './features/auth/AuthContext';
import { queryClient } from './lib/queryClient';

// Registers the PWA service worker (src/sw.ts) - `prompt` update behavior means a new
// version won't silently replace the running app; ProjectPlan.md §21 doesn't call for a
// custom update-available UI, so an unattended no-op callback is acceptable here.
if ('serviceWorker' in navigator) {
  registerSW({ immediate: true });
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <App />
      </AuthProvider>
    </QueryClientProvider>
  </StrictMode>,
);
