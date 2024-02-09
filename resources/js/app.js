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


