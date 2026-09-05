import { resolve } from 'node:path'

import vue from '@vitejs/plugin-vue'
import AutoImport from 'unplugin-auto-import/vite'
import { defineConfig } from 'vitest/config'

// Not mergeConfig(viteConfig): the app config pulls in the Laravel, Tailwind
// and PWA plugins, which want a dev server or build pipeline. Tests only need
// what changes how modules resolve and compile.
export default defineConfig({
  plugins: [
    AutoImport({
      imports: ['vue', 'vue-router'],
      dts: false,
      vueTemplate: true,
    }),
    vue(),
  ],
  resolve: {
    alias: {
      '~': resolve(__dirname, 'resources/js'),
    },
  },
  test: {
    // jsdom, not happy-dom: anything that sanitizes HTML needs a faithful
    // parser, and happy-dom's silently no-ops DOMPurify.
    environment: 'jsdom',
    // No globals: every test imports from vitest, so the checked-in tsconfig
    // needs no extra ambient types.
    include: ['tests/js/**/*.test.ts', 'packages/reactive-vue/tests/**/*.test.ts'],
    setupFiles: ['tests/js/setup.ts'],
  },
})
