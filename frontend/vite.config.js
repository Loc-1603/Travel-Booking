// Vite configuration for Hotel Booking frontend
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    rollupOptions: {
      output: {
        // Split heavy vendor libraries into their own chunks for better caching
        // and a smaller entry file (pages are code-split via React.lazy in App.jsx).
        // Function form: react/react-dom entries are ESM facades, so the object
        // form cannot reliably match them — match on resolved node_modules paths.
        manualChunks(id) {
          const p = id.replace(/\\/g, '/');
          if (!p.includes('/node_modules/')) return undefined;
          if (/\/node_modules\/(react|react-dom|scheduler)\//.test(p) || p.includes('/node_modules/react-jsx-runtime/')) return 'react-vendor';
          if (p.includes('/node_modules/react-router') || p.includes('/node_modules/@remix-run/')) return 'router';
          if (p.includes('/node_modules/@tanstack/')) return 'query';
          if (p.includes('/node_modules/leaflet') || p.includes('/node_modules/react-leaflet/') || p.includes('/node_modules/@react-leaflet/')) return 'map-vendor';
          return undefined;
        },
      },
    },
  },
})
