import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss','resources/scss/global.scss','resources/scss/index.scss','resources/scss/hero.scss','resources/scss/inicio.scss','resources/scss/vandu-inicio.scss','resources/scss/privacidad.scss','resources/scss/servicios.scss','resources/scss/contacto.scss','resources/scss/branding.scss','resources/scss/multimedia.scss','resources/scss/construccion.scss','resources/js/app.js','resources/js/bg.js','resources/js/hero.js','resources/js/vandu-inicio.js','resources/scss/barra.scss','resources/js/barra.js','resources/scss/whatsapp-flotante.scss','resources/js/whatsapp-flotante.js','resources/js/observer.js','resources/js/portafolio.js','resources/js/diseno.js','resources/js/bguno.js'],
            refresh: true,
        }),
    ],
});
