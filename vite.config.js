import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss','resources/scss/global.scss','resources/scss/index.scss','resources/scss/servicios.scss','resources/scss/contacto.scss','resources/scss/branding.scss','resources/scss/multimedia.scss','resources/scss/construccion.scss','resources/js/app.js','resources/js/bg.js','resources/js/observer.js','resources/js/portafolio.js','resources/js/diseno.js','resources/js/bguno.js'],
            refresh: true,
        }),
    ],
});
