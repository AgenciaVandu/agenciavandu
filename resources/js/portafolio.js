let observer = new IntersectionObserver(visibilidad, {});
let contenido = document.querySelector(".carrusel");


function visibilidad (entries){
    let entry = entries[0];
    if(entry.isIntersecting) {
         // el elemento es visible
        console.log("movimiento izquierda")
        contenido.classList.add("movimiento");
    } else {
        // el elemento no es visible
        console.log("movimiento derecha")
        contenido.classList.remove("movimiento");
    }
 }


observer.observe(contenido);