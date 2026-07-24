import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            // Both entry points build side by side during the Blade+Alpine ->
            // Inertia+React migration: app.js still serves every not-yet-ported
            // Blade page, app.jsx serves pages already converted to Inertia.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
});
