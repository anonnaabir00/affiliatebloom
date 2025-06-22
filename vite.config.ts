import { defineConfig } from 'vite'
import path from 'path'
import react from '@vitejs/plugin-react-swc'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    react(),
  ],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  build: {
    minify: true,
    manifest: false,
    rollupOptions: {
      input: {
        'main': path.resolve(__dirname, 'src/main.tsx'),
      },
      output: {
        dir: 'includes/assets/build',
        entryFileNames: '[name].js',
        assetFileNames: '[name].[ext]',
        manualChunks: undefined,
      },
    }
  }
})