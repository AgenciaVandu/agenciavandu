@extends('admin.layout')
@section('titulo', 'Punto de venta')
@section('contenido')
<div class="page-head"><div><h1>Punto de venta</h1><p class="sub">Escanea el código de barras o busca por nombre.</p></div><a href="{{ route('admin.pos.historial') }}" class="btn btn-borde">Historial</a></div>
<div class="row g-3"><div class="col-lg-7"><section class="panel"><div class="panel-body"><label for="buscar-pos" class="form-label">Código, clave o nombre</label><input id="buscar-pos" class="form-control" autofocus autocomplete="off" placeholder="Escanear producto"><div id="resultados-pos" class="d-grid gap-2 mt-3"></div></div></section></div><div class="col-lg-5"><section class="panel"><div class="panel-body"><h2 class="h5">Venta actual</h2><div id="carrito-pos">Sin productos.</div><div class="d-flex justify-content-between mt-3"><strong>Total</strong><strong id="total-pos"></strong></div><form method="post" action="{{ route('admin.pos.cobrar') }}" id="form-pos">@csrf<input type="hidden" name="solicitud_id" value="{{ $solicitudId }}"><div id="lineas-pos"></div><label class="form-label mt-3">Forma de pago</label><select class="form-select" name="forma_pago" id="forma-pos"><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="transferencia">Transferencia</option></select><div id="efectivo-pos"><label class="form-label mt-3">Recibido</label><input class="form-control" id="recibido-pos" type="number" step="0.01" min="0" name="recibido" value="0"></div><p id="cambio-pos" class="small mt-2"></p><button class="btn btn-primario w-100 mt-3" id="cobrar-pos" disabled>Cobrar y generar ticket</button></form></div></section></div></div>
@endsection
@push('scripts')
<script>
(()=>{'use strict';
const campo=document.getElementById('buscar-pos'),resultados=document.getElementById('resultados-pos'),carrito=document.getElementById('carrito-pos'),lineas=document.getElementById('lineas-pos'),total=document.getElementById('total-pos'),forma=document.getElementById('forma-pos'),recibido=document.getElementById('recibido-pos'),boton=document.getElementById('cobrar-pos');
const items=new Map(),dinero=n=>new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'}).format(n);
let peticion=0,espera;
function dibujar(){carrito.replaceChildren();lineas.replaceChildren();let suma=0,i=0;
for(const p of items.values()){const importe=Math.round(p.precio*100)*p.cantidad/100;suma+=importe;
const fila=document.createElement('div');fila.className='d-flex align-items-center gap-2 border-bottom py-2';
const nombre=document.createElement('span');nombre.className='flex-grow-1';nombre.textContent=p.nombre;
const cantidad=document.createElement('input');cantidad.className='form-control form-control-sm';cantidad.style.width='70px';cantidad.type='number';cantidad.min=1;cantidad.max=p.controla_existencia?Math.floor(p.existencia):99999;cantidad.value=p.cantidad;cantidad.onchange=()=>{p.cantidad=Math.max(1,Math.min(Number(cantidad.max),Math.floor(Number(cantidad.value)||1)));dibujar()};
const precio=document.createElement('span');precio.textContent=dinero(importe);
const quitar=document.createElement('button');quitar.type='button';quitar.className='btn btn-borde btn-sm';quitar.textContent='×';quitar.onclick=()=>{items.delete(p.id);dibujar()};
fila.append(nombre,cantidad,precio,quitar);carrito.append(fila);
for(const [k,v] of [['producto_id',p.id],['cantidad',p.cantidad]]){const h=document.createElement('input');h.type='hidden';h.name='productos['+i+']['+k+']';h.value=v;lineas.append(h)}i++}
if(!items.size)carrito.textContent='Sin productos.';
total.textContent=dinero(suma);boton.disabled=!items.size;
document.getElementById('cambio-pos').textContent=forma.value==='efectivo'?'Cambio: '+dinero(Math.max(0,Number(recibido.value||0)-suma)):'';
}
function agregar(p){const a=items.get(p.id);if(p.controla_existencia && Number(p.existencia)<(a?.cantidad||0)+1){alert('Existencia insuficiente');return}items.set(p.id,{...p,cantidad:(a?.cantidad||0)+1});campo.value='';resultados.replaceChildren();dibujar();campo.focus()}
async function buscar(){const q=campo.value.trim(),n=++peticion;resultados.replaceChildren();if(!q)return;
try{const r=await fetch(@json(route('admin.pos.buscar'))+'?q='+encodeURIComponent(q),{headers:{Accept:'application/json'}});if(!r.ok)throw Error('Error al buscar');const ps=await r.json();if(n!==peticion)return;const exacto=ps.find(p=>p.codigo_barras===q||p.clave===q);if(exacto){agregar(exacto);return}
for(const p of ps){const b=document.createElement('button');b.type='button';b.className='btn btn-borde text-start';b.textContent=p.nombre+' · '+dinero(p.precio);b.onclick=()=>agregar(p);resultados.append(b)}
if(!ps.length)resultados.textContent='No se encontraron productos.'}catch(e){resultados.textContent=e.message}}
campo.addEventListener('input',()=>{clearTimeout(espera);espera=setTimeout(buscar,250)});campo.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();clearTimeout(espera);buscar()}});
forma.onchange=()=>{document.getElementById('efectivo-pos').hidden=forma.value!=='efectivo';dibujar()};recibido.oninput=dibujar;document.getElementById('form-pos').onsubmit=e=>{if(!items.size){e.preventDefault();return}boton.disabled=true};dibujar();
})();
</script>
@endpush