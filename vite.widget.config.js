import { defineConfig } from 'vite';

/**
 * Widget-specific Vite configuration.
 * Builds resources/js/widget.js as IIFE bundle for <script> tag embedding.
 * Includes Echo and Pusher for WebSocket support.
 */
export default defineConfig({
    build: {
        lib: {
            entry: 'resources/js/widget.js',
            name: 'ChatWidgetSDK',
            formats: ['iife'],
            fileName: () => 'widget.js',
        },
        outDir: 'public',
        emptyOutDir: false,
        assetsDir: 'build/assets',
        rollupOptions: {
            output: {
                entryFileNames: 'js/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name === 'style.css') {
                        return 'css/widget.css';
                    }
                    return 'assets/[name][extname]';
                },
                // Ensure Echo and Pusher are bundled into the IIFE
                globals: {},
            },
        },
        minify: false, // esbuild not available in Vite v8 (rolldown-based)
        sourcemap: false,
    },
});
