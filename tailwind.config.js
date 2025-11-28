/** @type {import('tailwindcss').Config} */
import animate from 'tailwindcss-animate';
import daisyui from 'daisyui';

export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.vue",
        "./resources/**/*.js",
    ],
    theme: {
        extend: {},
    },
    // Keep some frequently-used classes (debug / dynamic) so they are not purged
    safelist: [
        'bg-linear-to-r', 'bg-gradient-to-r',
        'from-red-500', 'via-yellow-400', 'to-emerald-400',
        'text-white', 'py-1', 'text-sm', 'font-semibold'
    ],

    plugins: [
        animate,
        daisyui,
    ],
}