import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Geist', ...defaultTheme.fontFamily.sans],
                serif: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                vaytoven: {
                    pink: '#FF3D8A',
                    magenta: '#D63384',
                    purple: '#7B2CBF',
                    ink: '#1A1426',
                    paper: '#FBF8F3',
                },
            },
        },
    },
    plugins: [forms],
};
