import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    // Les badges de statut construisent leurs classes de couleur dynamiquement
    // (ex: "bg-{{ $sc }}-100 text-{{ $sc }}-700") : le scanner de contenu ne
    // voit pas ces noms de classe résolus, il faut donc les lister ici.
    safelist: [
        {
            pattern: /(bg|text|border)-(gray|red|yellow|green|blue|indigo|amber)-(50|100|200|700)/,
        },
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
