<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\OrdenCompra;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Buscador de todo el panel: clientes, proyectos, proveedores, cotizaciones y órdenes de compra.
 * Cada palabra tiene que aparecer en algún campo ("hotel ana" encuentra a Ana López de Hotel X).
 * Solo devuelve lo de las secciones que la persona puede ver.
 */
class Buscador
{
    public static function buscar(string $q, User $u, int $porGrupo = 5): array
    {
        $palabras = static::palabras($q);
        if (! $palabras) return [];

        $grupos = [];

        if ($u->puede('clientes')) {
            $grupos[] = static::grupo('clientes', 'Clientes', 'bi-people',
                Cliente::query(), $palabras, ['nombre', 'empresa', 'email', 'rfc', 'razon_social'], 'telefono', $porGrupo,
                fn ($q) => $q->orderByDesc('nuevo')->orderBy('empresa')->orderBy('nombre'),
                fn (Cliente $c) => [
                    'titulo' => $c->empresa ?: $c->nombre,
                    'sub'    => collect([$c->empresa ? $c->nombre : null, $c->telefono, $c->email])->filter()->implode(' · ') ?: 'Cliente',
                    'url'    => route('admin.clientes.show', $c),
                ]);
        }

        if ($u->puede('proyectos')) {
            $grupos[] = static::grupo('proyectos', 'Proyectos', 'bi-kanban',
                Proyecto::query()->with('cliente:id,nombre,empresa'), $palabras, ['nombre'], null, $porGrupo,
                fn ($q) => $q->orderByRaw("CASE WHEN estado = 'activo' THEN 0 WHEN estado = 'pausado' THEN 1 ELSE 2 END")->latest('id'),
                fn (Proyecto $p) => [
                    'titulo'   => $p->nombre,
                    'sub'      => collect([$p->cliente?->empresa ?: $p->cliente?->nombre, $p->tipo_nombre])->filter()->implode(' · '),
                    'url'      => route('admin.proyectos.show', $p),
                    'etiqueta' => Proyecto::ESTADOS[$p->estado] ?? null,
                ],
                fn (Builder $w, string $p) => $w->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%$p%")->orWhere('empresa', 'like', "%$p%")));
        }

        if ($u->puede('proveedores')) {
            $grupos[] = static::grupo('proveedores', 'Proveedores', 'bi-truck',
                Proveedor::query(), $palabras, ['empresa', 'nombre', 'rubro', 'email', 'rfc'], 'telefono', $porGrupo,
                fn ($q) => $q->orderBy('empresa'),
                fn (Proveedor $p) => [
                    'titulo' => $p->empresa,
                    'sub'    => collect([$p->rubro, $p->nombre, $p->telefono])->filter()->implode(' · ') ?: 'Proveedor',
                    'url'    => route('admin.proveedores.show', $p),
                ]);
        }

        if ($u->puede('cotizaciones')) {
            $grupos[] = static::grupo('cotizaciones', 'Cotizaciones', 'bi-file-earmark-text',
                Presupuesto::query()->with('conceptos'), $palabras, ['folio', 'cliente_nombre', 'cliente_empresa', 'titulo'], null, $porGrupo,
                fn ($q) => $q->latest('id'),
                fn (Presupuesto $p) => [
                    'titulo'   => $p->folio . ' · ' . ($p->cliente_empresa ?: $p->cliente_nombre),
                    'sub'      => Str::limit($p->conceptos->first()?->resumen ?: $p->titulo, 70) . ' · ' . $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal),
                    'url'      => route('admin.presupuestos.edit', $p),
                    'etiqueta' => Presupuesto::ESTADOS[$p->estado] ?? null,
                ],
                fn (Builder $w, string $p) => $w->orWhereHas('conceptos', fn ($c) => $c->where('descripcion', 'like', "%$p%")->orWhere('titulo', 'like', "%$p%")));
        }

        if ($u->puede('proveedores')) {
            $grupos[] = static::grupo('ordenes', 'Órdenes de compra', 'bi-receipt',
                OrdenCompra::query()->with(['proveedor:id,empresa', 'conceptos']), $palabras, ['folio', 'titulo'], null, $porGrupo,
                fn ($q) => $q->latest('id'),
                fn (OrdenCompra $o) => [
                    'titulo'   => $o->folio . ' · ' . $o->titulo,
                    'sub'      => $o->proveedor?->empresa . ' · ' . OrdenCompra::dinero($o->total),
                    'url'      => route('admin.ordenes.show', $o),
                    'etiqueta' => $o->estado_texto,
                ],
                fn (Builder $w, string $p) => $w->orWhereHas('proveedor', fn ($x) => $x->where('empresa', 'like', "%$p%"))
                    ->orWhereHas('conceptos', fn ($x) => $x->where('descripcion', 'like', "%$p%")));
        }

        return array_values(array_filter($grupos, fn ($g) => $g['total'] > 0));
    }

    /** Palabras de la búsqueda (máximo 5), sin comodines de SQL */
    public static function palabras(string $q): array
    {
        $q = trim(preg_replace('/\s+/u', ' ', str_replace(['%', '_', '\\'], ' ', $q)));
        if (mb_strlen($q) < 2) return [];
        return array_slice(array_values(array_filter(explode(' ', $q), fn ($p) => $p !== '')), 0, 5);
    }

    private static function grupo(string $clave, string $titulo, string $icono, Builder $consulta, array $palabras, array $campos,
                                  ?string $campoTel, int $limite, callable $orden, callable $fila, ?callable $extra = null): array
    {
        foreach ($palabras as $p) {
            $consulta->where(function (Builder $w) use ($p, $campos, $campoTel, $extra) {
                foreach ($campos as $c) $w->orWhere($c, 'like', "%$p%");
                // Teléfonos: "9991234567" encuentra "(999) 123 4567"
                $digitos = preg_replace('/\D/', '', $p);
                if ($campoTel && strlen($digitos) >= 4 && strlen($digitos) >= mb_strlen($p) - 2) {
                    $w->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($campoTel, ' ', ''), '-', ''), '(', ''), ')', ''), '.', '') LIKE ?", ["%$digitos%"]);
                }
                if ($extra) $extra($w, $p);
            });
        }
        $total = (clone $consulta)->count();
        $items = $total ? $orden($consulta)->limit($limite)->get()->map($fila)->all() : [];

        return compact('clave', 'titulo', 'icono', 'total', 'items');
    }
}
