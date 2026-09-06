import { resolve } from 'node:path'

import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import AutoImport from 'unplugin-auto-import/vite'
import { defineConfig } from 'vite'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig(({ mode }) => ({
  plugins: [
    laravel({ input: ['resources/js/main.ts'], refresh: true }),
    // Only the framework APIs. Composables are imported explicitly: a
    // directory auto-import silently drops a composable from the map when it
    // imports a sibling, and every other call site becomes a bare global
    // that typecheck, lint and tests all miss.
    AutoImport({
      imports: ['vue', 'vue-router'],
      dts: 'resources/js/types/auto-imports.d.ts',
      vueTemplate: true,
    }),
    vue(),
    tailwindcss(),
    // Included but off: set VITE_PWA=1 to register the service worker.
    VitePWA({
      disable: process.env.VITE_PWA !== '1',
      registerType: 'autoUpdate',
      buildBase: '/build/',
      scope: '/',
      base: '/',
      workbox: {
        navigateFallback: '/',
        navigateFallbackDenylist: [/^\/(api|auth|rq|horizon|docs)(\/|$)/],
      },
      manifest: {
        name: 'Kit',
        short_name: 'Kit',
        start_url: '/',
        display: 'standalone',
        theme_color: '#ffffff',
        background_color: '#ffffff',
      },
    }),
  ],
  resolve: {
    alias: {
      '~': resolve(__dirname, 'resources/js'),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    // The SPA is served by Laravel; the dev server only serves assets and
    // proxies nothing. Open http://localhost:8000, not the Vite port.
  },
  build: {
    target: 'baseline-widely-available',
    rollupOptions: {
      output: {
        // Stable vendors in their own chunks so they stay cached across
        // deploys instead of being invalidated with every app change.
        advancedChunks: {
          groups: [
            { name: 'vue', test: /node_modules[\\/](vue|@vue|vue-router|vue-i18n)[\\/]/ },
            { name: 'query', test: /node_modules[\\/]@tanstack[\\/]/ },
            { name: 'reka', test: /node_modules[\\/]reka-ui[\\/]/ },
            { name: 'icons', test: /node_modules[\\/]@iconify(-json)?[\\/]/ },
            { name: 'echo', test: /node_modules[\\/](laravel-echo|pusher-js)[\\/]/ },
          ],
        },
      },
    },
  },
  define: {
    __DEV__: JSON.stringify(mode !== 'production'),
  },
}))
