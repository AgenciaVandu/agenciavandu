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
                    <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Reiciendis soluta tempora officiis totam eaque, commodi impedit enim quidem esse dolores ipsam provident sapiente sunt magni, ab aut minima beatae distinctio!</p>
                    <div class="contato darktxt">
                        <p class="correo darktxt">team@agenciavandu.com</p>
                        <button>
                            <img src="{{asset('/img/copy.svg')}}" width="20" alt="icono copiar">
                        </button>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12 col-sm-12">
                    {{$clientes ?? ''}}
                </div>
            </div>
        </div>
    </section>