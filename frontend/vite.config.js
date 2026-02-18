import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        vue(),
        tailwindcss()
    ],
    root: './',
    build: {
        outDir: '../dist',
        emptyOutDir: true,
        rollupOptions: {
            input: './src/main.js',
            output: {
                // Feste Dateinamen ohne Hash — für einfache PHP-Einbindung
                entryFileNames: 'app.js',
                chunkFileNames: 'chunks/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return 'app.css'
                    }
                    return 'assets/[name][extname]'
                }
            }
        }
    },
    // Dev-Server Proxy für PHP-API (optional: PHP läuft auf anderem Port)
    server: {
        proxy: {
            '/api': {
                target: 'http://localhost:80',
                changeOrigin: true
            }
        }
    }
})
