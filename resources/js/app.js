import './bootstrap';
import '../scss/app.scss'
import * as bootstrap from 'bootstrap'

window.addEventListener("scroll", function() {
    var nav = document.querySelector("nav");
    nav.classList.toggle("dark", window.scrollY > 0, {passive: true});
})

let observer = new IntersectionObserver(visibilidad, {});
let portafolio = document.querySelector(".base", ".middle");


function visibilidad (entries){
    let entry = entries[0];
    if(entry.isIntersecting) {
         // el elemento es visible
        console.log("visible")
        portafolio.classList.add("active");
    } else {
        // el elemento no es visible
        console.log("no es visible")
        portafolio.classList.remove("active");
    }
 }


observer.observe(portafolio);

