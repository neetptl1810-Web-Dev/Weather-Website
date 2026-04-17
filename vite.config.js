import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',      // Keep for global/base styles (optional)
                'resources/js/app.js',        // Keep for global scripts (optional)
                'resources/css/weather.css',  // ✅ Weather page CSS
                'resources/js/weather.js',    // ✅ Weather page JS
            ],
            refresh: true,
        }),
    ],
});