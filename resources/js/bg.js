window.addEventListener("scroll", function() {
    var body = document.querySelector("body");
    body.classList.toggle("bgdark", window.scrollY > 1600, {passive: true});

    var texto = document.querySelector(".darktxt");
    texto.classList.toggle("txtdark", window.scrollY > 1500, {passive: true});

})

