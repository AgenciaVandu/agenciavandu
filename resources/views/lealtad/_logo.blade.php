<span class="logo">@if($c->logo(true))<img src="{{ $c->logo(true) }}" alt="">@else{{ mb_strtoupper(mb_substr($c->nombre, 0, 1)) }}@endif</span>
