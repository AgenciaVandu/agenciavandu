// Inicio de agenciavandu.com: arranca cada parte. Cada una vive en resources/js/inicio/.
// La barra (barra.js) y el botón de WhatsApp (whatsapp-flotante.js) son del sitio y cargan desde su componente.
import { alCargar } from './inicio/util';
import scrollSuave from './inicio/scroll';
import titulares from './inicio/titulares';
import apariciones from './inicio/apariciones';
import planos from './inicio/planos';
import nerby from './inicio/nerby';
import proceso from './inicio/proceso';
import contacto from './inicio/contacto';
import riel from './inicio/riel';
import cursor from './inicio/cursor';

window.vxListo = true;

alCargar(() => {
    scrollSuave();
    titulares();
    apariciones();
    planos();
    nerby();
    proceso();
    contacto();
    riel();
    cursor();
});
