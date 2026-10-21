{{-- Submenú de la agenda del gimnasio --}}
@php $u = auth()->user(); @endphp
<nav class="subnav-fx segmento mb-4" aria-label="Agenda del gimnasio">
    <a href="{{ route('admin.clases.index') }}" class="{{ request()->routeIs('admin.clases.index', 'admin.clases.show', 'admin.clases.edit', 'admin.clases.create') ? 'activo' : '' }}"><i class="bi bi-calendar-week"></i> Agenda</a>
    @if($u->puede('clases'))
        <a href="{{ route('admin.clases.series') }}" class="{{ request()->routeIs('admin.clases.series*') ? 'activo' : '' }}"><i class="bi bi-arrow-repeat"></i> Horario fijo</a>
        <a href="{{ route('admin.clase-tipos') }}" class="{{ request()->routeIs('admin.clase-tipos*') ? 'activo' : '' }}"><i class="bi bi-tags"></i> Tipos de clase</a>
        <a href="{{ route('admin.ejercicios') }}" class="{{ request()->routeIs('admin.ejercicios*') ? 'activo' : '' }}"><i class="bi bi-list-check"></i> Ejercicios</a>
    @endif
</nav>
