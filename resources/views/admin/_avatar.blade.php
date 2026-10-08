@php
    // Iniciales con un tono fijo por nombre (el mismo cliente siempre tiene el mismo color)
    $tonos = [['#E8EEFC', '#2448B8'], ['#E3FBEF', '#04704A'], ['#FFF1DE', '#9A5300'], ['#F3E8FD', '#6E32B5'], ['#FDE8EF', '#B0275B'], ['#E2F5F8', '#0C6A7A'], ['#EEF0F3', '#3F4553']];
    $txt = trim($nombre ?? '?');
    [$fondo, $tinta] = $tonos[abs(crc32(mb_strtolower($txt))) % count($tonos)];
    $ini = collect(preg_split('/\s+/', preg_replace('/[^\pL\pN\s]/u', '', $txt)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') ?: '?';
@endphp
<span class="avatar" style="background: {{ $fondo }}; color: {{ $tinta }}" aria-hidden="true">{{ $ini }}</span>
