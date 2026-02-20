<nav class="navbar navbar-expand-lg">
  <div class="container">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTogglerDemo03" aria-controls="navbarTogglerDemo03" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <a class="navbar-brand" href="#">
      <img src="{{asset('img/logo-vandu.svg')}}" width="100" alt="Logotipo agencia vandu">
    </a>
    <div class="collapse navbar-collapse" id="navbarTogglerDemo03">
      <ul class="navbar-nav m-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" aria-current="page" href="{{route ('home')}}">Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{route ('servicios')}}">Web</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{route ('audiovisuales')}}">Audiovisuales</a>
        </li>
        {{-- <li class="nav-item">
          <a class="nav-link" href="{{route ('productos')}}">Productos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{route ('alianzas')}}">Alianzas</a>
        </li> --}}
        <li class="nav-item">
          <a class="nav-link" href="{{route ('cotizar')}}">Contacto</a>
        </li>
      </ul>
      <div class="redes">
        {{-- <li>
          <a href="https://www.facebook.com/vandumx">
            <img src="{{asset ('img/facebook.svg') }}" width="20" alt="Logotipo Facebook">
          </a>
        </li> --}}
       {{--  <li>
          <a href="https://www.instagram.com/vandumx">
          <img src="{{asset ('img/instagram.svg') }}" width="20" alt="Logotipo Facebook">
          </a>
        </li> --}}
        {{-- <li>
          <a href="https://www.behance.net/vandumx">
          <img src="{{asset ('img/whatsapp.svg') }}" width="20" alt="Logotipo Facebook">
          </a>
        </li> --}}
      </div>
    </div>
  </div>
</nav>
