import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Noto Nastaliq/Naskh can be layered in later for Urdu.
                sans: ['Figtree', 'Segoe UI', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // PFA green accent (placeholder — see config/pfa.php 'panel.accent').
                pfa: {
                    50: '#eafaf3',
                    100: '#d0f2e2',
                    200: '#a4e5c8',
                    500: '#0B6E4F',
                    600: '#095c42',
                    700: '#074a35',
                    800: '#053726',
                },
            },
        },
    },
    plugins: [forms],
};
