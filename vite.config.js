import { defineConfig } from 'vite'
import path from 'path'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
      react(),
      tailwindcss(),
  ],
  build: {
    minify: true,
    manifest: false,
    rollupOptions: {
      input: {
        'frontend': path.resolve(__dirname, 'src/main.jsx'),
        // 'admin': path.resolve(__dirname, 'src/admin.jsx'),
      },

      output:{
        dir: 'includes/assets/build',
        watch: true,
        entryFileNames: '[name].js',
        assetFileNames: '[name].[ext]',
        manualChunks: undefined,
      },

    }
  }
})