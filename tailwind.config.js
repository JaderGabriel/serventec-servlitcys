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
                sans: ['DM Sans', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Outfit', 'DM Sans', ...defaultTheme.fontFamily.sans],
            },
            animation: {
                float: 'float 7s ease-in-out infinite',
                'float-delayed': 'float 9s ease-in-out infinite 1s',
            },
            keyframes: {
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-12px)' },
                },
            },
        },
    },

    plugins: [forms],
};
