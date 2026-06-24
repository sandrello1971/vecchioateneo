import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // GLITCH landing (P24) — self-hosted via @fontsource, vedi
                // resources/css/glitch-landing.css. Usate solo dalla landing.
                display: ['"Playfair Display"', ...defaultTheme.fontFamily.serif],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Palette brand GLITCH (theglitchworld.it) per la landing pubblica.
                glitch: {
                    black: '#0a0a0a',
                    ivory: '#f2efe9',
                    red: '#ef2d56',
                },
            },
        },
    },

    plugins: [forms],
};
