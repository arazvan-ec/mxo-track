import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: '/app/',
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    outDir: '../backend/public/app',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'index.html'),
        'sidebar-widget': path.resolve(__dirname, 'sidebar-widget.html'),
        'topbar-widget': path.resolve(__dirname, 'topbar-widget.html'),
        'dashboard-widget': path.resolve(__dirname, 'dashboard-widget.html'),
      },
      output: {
        entryFileNames: (chunkInfo) => {
          if (chunkInfo.name === 'sidebar-widget') {
            return 'assets/sidebar-widget.js';
          }
          if (chunkInfo.name === 'topbar-widget') {
            return 'assets/topbar-widget.js';
          }
          if (chunkInfo.name === 'dashboard-widget') {
            return 'assets/dashboard-widget.js';
          }
          return 'assets/[name]-[hash].js';
        },
      },
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://localhost:8000',
      '/login': 'http://localhost:8000',
      '/logout': 'http://localhost:8000',
    },
  },
})
