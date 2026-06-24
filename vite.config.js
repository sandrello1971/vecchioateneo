import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Landing pubblica GLITCH (P24): bundle CSS isolato dall'app interna.
                'resources/css/glitch-landing.css',
            ],
            refresh: true,
        }),
    ],
});
