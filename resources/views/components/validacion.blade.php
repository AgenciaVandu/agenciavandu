<section class="aliados">
    <div class="container">
        <div class="titular">
            <h1 class="text-center">Somos una agencia validada <br><span style="color: #13161D;">por las mejores marcas</span></h1>
            {{$logos ?? ''}}
        </div>
    </div>
</section>
<section class="clientes">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 col-md-12 col-sm-12 titular m-auto">
                <h1>¿Platicamos sobre <br class="ocultar" /> tu proyecto?</h1>
                <p>Cada proyecto sin importar el tamaño tiene requerimientos diferentes, acércate a nuestros expertos que te guiarán en el proceso de saber que es lo mejor para ti y para tus objetivos finales. </p>
                <div class="contato darktxt">
                    <input type="text" id="url" class="correo darktxt" value="team@agenciavandu.com"></input>
                    <button>
                        <img src="{{asset('/img/copy.svg')}}" class="copiado" width="20" alt="icono copiar">
                    </button>
                    <span id="message" </span>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 col-sm-12 mt-2">
                {{$clientes ?? ''}}
            </div>
        </div>
    </div>
</section>