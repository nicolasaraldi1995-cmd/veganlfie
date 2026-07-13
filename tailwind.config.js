import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                surface: {
                    DEFAULT: '#f3f1ec',
                    1: '#ffffff',
                    2: '#e8e5de',
                    3: '#dbd8d0',
                    4: '#ccc9c1',
                },
                accent: {
                    DEFAULT: '#1f8f80',
                    dim: '#166b60',
                    bright: '#2ab5a5',
                    muted: 'rgba(31,143,128,0.10)',
                },
                text: {
                    DEFAULT: '#1a1d21',
                    secondary: '#52565e',
                    muted: '#8e919a',
                },
                border: {
                    DEFAULT: 'rgba(0,0,0,0.10)',
                    hover: 'rgba(0,0,0,0.18)',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                xl: '12px',
                '2xl': '12px',
                '3xl': '12px',
            },
        },
    },

    plugins: [forms],
};
