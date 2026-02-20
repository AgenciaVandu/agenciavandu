window.addEventListener("scroll", function() {
    var body = document.querySelector("body");
    body.classList.toggle("bgdark", window.scrollY > 100, {passive: true});

    var texto = document.querySelector(".darktxt");
    texto.classList.toggle("txtdark", window.scrollY > 200, {passive: true});

})

window.onscroll = function() {
  const nav = document.querySelector('.logos-container');
  if (window.scrollY > 100) {
    nav.classList.add('scrolled');
  } else {
    nav.classList.remove('scrolled');
  }
};
