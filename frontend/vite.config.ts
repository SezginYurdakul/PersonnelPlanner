import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    VitePWA({
      // Custom `push`/`notificationclick` handlers (src/sw.ts) require a handwritten
      // service worker - the default `generateSW` strategy can't inject arbitrary
      // listeners (ProjectPlan.md §21.2).
      strategies: 'injectManifest',
      srcDir: 'src',
      filename: 'sw.ts',
      injectManifest: {
        // Keep the precached app-shell manifest scoped to what's actually needed for the
        // employee schedule view to open offline - not a general offline-first app.
        globPatterns: ['**/*.{js,css,html}'],
      },
      registerType: 'prompt',
      manifest: {
        name: 'Bakkerij X',
        short_name: 'Bakkerij X',
        description: 'Bakkerij X - Personeelsplanning',
        theme_color: '#3D2817',
        background_color: '#FAF9F5',
        display: 'standalone',
        start_url: '/me/schedule',
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
          {
            src: '/icons/icon-maskable-512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
    }),
  ],
  server: {
    allowedHosts: ['localhost', 'frontend', '.docker.internal'],
  },
})
