import './bootstrap';
import '../scss/app.scss'
import * as bootstrap from 'bootstrap'

const observador = new IntersectionObserver (
    entries => {
        entries.forEach(entry => {
            if  (entry.isIntersecting) {
                entry.target.classList.add('show')
                observador.unobserve(entry.target)
            } else {
                entry.target.classList.remove('show')
            }
        });
    },
    {
        threshold: 0.3,
    }
);

const sections = document.querySelectorAll('section');

sections.forEach(section => {
    observador.observe(section)
});

//////// copy mail
const button = document.querySelector('img.copiado');

const input = document.querySelector('.correo');

button.addEventListener('click', function()
{
    input.focus();
    document.execCommand('selectAll');
    document.execCommand('copy');

    message.innerHTML = "¡Copiado!";
    setTimeout(()=> message.innerHTML = "", 3000)
})

const cursor = document.querySelector(".cursor");

document.addEventListener("mousemove", (e) =>  {
    let x = e.pageX;
    let y = e.pageY;

    cursor.style.top = y + "px";
    cursor.style.left = x + "px";

})
