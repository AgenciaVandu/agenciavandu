import './bootstrap';
import '../scss/app.scss'
import * as bootstrap from 'bootstrap'

window.addEventListener("scroll", function() {
    var nav = document.querySelector("nav");
    nav.classList.toggle("dark", window.scrollY > 0, {passive: true});
})



