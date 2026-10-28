@extends('admin.layout')
@section('titulo', 'Inicio')
@section('sin_regresar', true)

@php
    $h = (int) now(config('vandu.zona_horaria'))->format('G');
    $saludo = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');
@endphp

@section('contenido')
<div class="page-head">
    <div><h1>{{ $saludo }}, {{ auth()->user()->primer_nombre }}</h1><p class="sub">{{ ucfirst(now(config('vandu.zona_horaria'))->locale('es')->isoFormat('dddd D [de] MMMM')) }} · tu día en {{ \App\Support\Cuentas::actual()?->nombre }}</p></div>
</div>

@include('admin._hoy')

<h2 class="fs-6 fw-semibold secundario text-uppercase mb-3" style="letter-spacing: .05em">Tus herramientas</h2>
<div class="md-accesos">
    @foreach($accesos as $a)
        <a href="{{ $a['url'] }}" class="md-acceso"><i class="bi {{ $a['icono'] }}"></i><span><b>{{ $a['titulo'] }}</b><span>{{ $a['ayuda'] }}</span></span></a>
    @endforeach
</div>
<style>
    .md-accesos { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
    .md-acceso { display: flex; gap: 12px; align-items: center; padding: 16px 18px; border-radius: 16px; border: 1px solid var(--line); background: #fff; color: inherit; text-decoration: none; }
    .md-acceso:hover { border-color: var(--marca); }
    .md-acceso i { font-size: 21px; width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: var(--marca-soft); color: var(--marca); flex: none; }
    .md-acceso b { display: block; font-size: 15px; } .md-acceso span span { font-size: 13px; color: var(--muted); }
</style>
@endsection
