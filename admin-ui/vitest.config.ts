import { fileURLToPath, URL } from 'node:url'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  // The `@vitejs/plugin-vue` types resolve against the root `vite` install,
  // while `vitest/config.defineConfig` expects the `vitest/node_modules/vite`
  // copy. The values are structurally identical at runtime; cast to `any`
  // to keep the build type-checker happy without dragging in a separate
  // import path.
  plugins: [vue() as any],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test-setup.ts'],
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
  },
})
