let observer = new IntersectionObserver(visibilidad, {});
let portafolio = document.querySelector(".base");


function visibilidad (entries){
    let entry = entries[0];
    if(entry.isIntersecting) {
         // el elemento es visible
        console.log("visible")
        portafolio.classList.add("activo");
    } else {
        // el elemento no es visible
        console.log("no es visible")
        portafolio.classList.remove("activo");
    }
 }


observer.observe(portafolio);