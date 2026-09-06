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
    // On for production builds; in dev set VITE_PWA=1 to register the worker
    // and test the install flow. The manifest link and the icons are in
    // resources/views/app.blade.php and public/icons, because Laravel serves
    // the HTML, not Vite.
    VitePWA({
      disable: mode !== 'production' && process.env.VITE_PWA !== '1',
      registerType: 'prompt',
      buildBase: '/build/',
      scope: '/',
      base: '/',
      includeAssets: ['favicon.ico', 'apple-touch-icon.png', 'icons/*.svg'],
      workbox: {
        navigateFallback: '/',
        navigateFallbackDenylist: [/^\/(api|auth|rq|horizon|docs|build)(\/|$)/],
        // Assets are hashed, so precaching them is safe; everything else is
        // fetched live, because a stale API answer is worse than no answer.
        globPatterns: ['**/*.{js,css,woff2}'],
        cleanupOutdatedCaches: true,
      },
      manifest: {
        id: '/',
        name: 'Kit',
        short_name: 'Kit',
        description: 'Kit',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        display_override: ['standalone', 'minimal-ui'],
        orientation: 'any',
        lang: 'en',
        theme_color: '#f0f1f4',
        background_color: '#f0f1f4',
        categories: ['productivity', 'business'],
        icons: [
          { src: '/icons/icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
          {
            src: '/icons/icon-maskable-512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
        // Long-press targets on the home screen icon.
        shortcuts: [
          {
            name: 'Users',
            url: '/users',
            icons: [{ src: '/icons/shortcut-users.png', sizes: '96x96' }],
          },
          {
            name: 'Account',
            url: '/account/profile',
            icons: [{ src: '/icons/shortcut-account.png', sizes: '96x96' }],
          },
        ],
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
