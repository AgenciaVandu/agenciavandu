<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Credito as Calculo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lote de ejemplo para que un negocio nuevo vea todo funcionando el primer día:
 * 10 unidades (5 vendidas a crédito, 1 apartada, 4 disponibles) y 5 clientes:
 * 2 al corriente, 1 con una letra vencida y 2 con varias. Las fechas se calculan a partir de hoy.
 * Todo queda marcado como ejemplo y se quita con un botón.
 */
class AutosEjemplo
{
    // marca, modelo, versión, año, tipo, color, km, transmisión, combustible, precio, foto
    public const UNIDADES = [
        ['Nissan', 'Versa', 'Advance', 2021, 'Sedán', 'Rojo', 48500, 'Automática', 'Gasolina', 219000, 'versa'],
        ['Volkswagen', 'Jetta', 'Comfortline', 2019, 'Sedán', 'Azul', 72300, 'Automática', 'Gasolina', 259000, 'jetta'],
        ['Toyota', 'RAV4', 'XLE', 2020, 'SUV', 'Blanco perla', 54100, 'Automática', 'Gasolina', 419000, 'rav4'],
        ['Mazda', 'CX-5', 'i Grand Touring', 2021, 'SUV', 'Rojo cobrizo', 39800, 'Automática', 'Gasolina', 449000, 'cx5'],
        ['Chevrolet', 'Aveo', 'LT', 2022, 'Sedán', 'Gris plata', 22600, 'Estándar', 'Gasolina', 209000, 'aveo'],
        ['Kia', 'Rio', 'EX', 2020, 'Hatchback', 'Blanco', 61200, 'Automática', 'Gasolina', 199000, 'rio'],
        ['Honda', 'CR-V', 'Touring', 2019, 'SUV', 'Gris oxford', 78400, 'Automática', 'Gasolina', 399000, 'crv'],
        ['Toyota', 'Hilux', 'SR', 2021, 'Pickup', 'Blanco', 66000, 'Estándar', 'Diésel', 469000, 'hilux'],
        ['Toyota', 'Corolla', 'LE', 2020, 'Sedán', 'Negro', 58900, 'CVT', 'Gasolina', 289000, 'corolla'],
        ['Suzuki', 'Swift', 'GLX', 2022, 'Hatchback', 'Azul', 18300, 'Estándar', 'Gasolina', 239000, 'swift'],
    ];

    // cliente, teléfono, unidad (índice), hace cuántos meses se vendió, días de corrimiento, plazo, letras vencidas sin pagar
    // (el corrimiento reparte los vencimientos: unos ya cobrados este mes y otros por vencer esta semana)
    public const VENTAS = [
        ['María Fernanda Pech Cauich', '999 214 3871', 0, 7, 3, 24, 0],
        ['José Luis Canché May', '999 302 1594', 8, 4, -8, 36, 0],
        ['Andrea Chan Uc', '999 457 2210', 4, 6, -5, 24, 1],
        ['Roberto Ek Balam', '999 168 9043', 1, 9, 1, 24, 3],
        ['Gabriela Tun Poot', '999 533 7718', 5, 8, -12, 36, 4],
    ];

    public const DESCRIPCIONES = [
        'Único dueño, servicios de agencia al día y factura original. Llantas al 80 %.',
        'Mantenimiento reciente, interiores impecables y sin detalles de pintura.',
        'Segundo dueño, todos los servicios en agencia. Lista para entregarse.',
    ];
    public const FICHAS = [
        'Sedán'     => "Motor 1.6 L\nAire acondicionado\nPantalla táctil con Apple CarPlay y Android Auto\nCámara de reversa\nBolsas de aire frontales y laterales\nRines de aluminio",
        'Hatchback' => "Motor 1.4 L\nAire acondicionado\nBluetooth y USB\nVidrios y espejos eléctricos\nBolsas de aire frontales\nFrenos ABS",
        'SUV'       => "Motor 2.5 L\nAire acondicionado de doble zona\nPantalla con Apple CarPlay y Android Auto\nCámara de reversa y sensores\nQuemacocos\nAsientos de piel\nControl de crucero",
        'Pickup'    => "Motor 2.4 L diésel\nCaja de 1 tonelada\nAire acondicionado\nBluetooth\nGancho de arrastre\nProtector de batea",
    ];

    public static function hay(): bool
    {
        return Unidad::where('ejemplo', true)->exists();
    }

    /** Carga el lote de ejemplo en la cuenta activa; regresa cuántas unidades agregó */
    public static function cargar(): int
    {
        if (self::hay()) return 0;
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz)->startOfDay();
        $vendedor = User::orderBy('id')->first();

        return DB::transaction(function () use ($hoy, $vendedor) {
            $unidades = [];
            foreach (self::UNIDADES as $i => [$marca, $modelo, $version, $anio, $tipo, $color, $km, $trans, $comb, $precio, $foto]) {
                $u = Unidad::create([
                    'marca' => $marca, 'modelo' => $modelo, 'version' => $version, 'anio' => $anio, 'tipo' => $tipo, 'color' => $color,
                    'kilometraje' => $km, 'transmision' => $trans, 'combustible' => $comb, 'precio' => $precio, 'costo' => round($precio * 0.83, -3),
                    'placas' => 'YUC-' . (412 + $i * 37) . '-' . chr(65 + $i), 'serie' => '3N1' . strtoupper(substr(md5($marca . $modelo . $i), 0, 14)),
                    'descripcion' => self::DESCRIPCIONES[$i % 3], 'ficha' => self::FICHAS[$tipo] ?? '', 'vendedor_id' => $vendedor?->id,
                    'estado' => 'disponible', 'publico' => true, 'ejemplo' => true,
                ]);
                $ruta = resource_path("autos-ejemplo/$foto.jpg");
                if (is_file($ruta)) {
                    try { FotoUnidad::agregar($u, new UploadedFile($ruta, "$foto.jpg", 'image/jpeg', null, true)); } catch (\Throwable) {}
                }
                $unidades[] = $u;
            }
            $unidades[2]->update(['estado' => 'apartado']);   // RAV4 apartada

            $a = Calculo::ajustes();
            foreach (self::VENTAS as [$nombre, $tel, $idx, $meses, $dias, $plazo, $vencidas]) {
                $u = $unidades[$idx];
                $cliente = Cliente::create(['nombre' => $nombre, 'telefono' => $tel, 'origen' => 'Venta de auto', 'ejemplo' => true, 'nuevo' => false]);
                $fecha = $hoy->copy()->subMonthsNoOverflow($meses)->addDays($dias);
                $enganche = round($u->precio * 0.3, -3);
                $financiado = $u->precio - $enganche;
                $c = Credito::create([
                    'unidad_id' => $u->id, 'cliente_id' => $cliente->id, 'vendedor_id' => $vendedor?->id, 'tipo' => 'credito', 'fecha' => $fecha->toDateString(),
                    'precio' => $u->precio, 'enganche' => $enganche, 'financiado' => $financiado, 'tasa' => $a['tasa'], 'plazo' => $plazo,
                    'mensualidad' => Calculo::mensualidad($financiado, $a['tasa'], $plazo), 'moratorio_modo' => $a['moratorio_modo'], 'moratorio_valor' => $a['moratorio_valor'],
                    'dias_gracia' => $a['dias_gracia'], 'descuento_adelanto' => $a['descuento_adelanto'], 'estado' => 'activo',
                ]);
                foreach (Calculo::tabla($financiado, $a['tasa'], $plazo, $fecha->copy()->addMonthNoOverflow()) as $f) {
                    $c->letras()->create(collect($f)->except('saldo')->all());
                }
                $u->update(['estado' => 'vendido']);

                // Paga todas las letras vencidas menos las que debe; los morosos pagaron algunas tarde (con moratorio)
                $vencidasHoy = $c->letras()->where('vence_el', '<', $hoy->copy()->subDays((int) $c->dias_gracia)->toDateString())->count();
                $pagar = max(0, $vencidasHoy - $vencidas);
                for ($k = 1; $k <= $pagar; $k++) {
                    $letra = $c->letras()->whereNull('pagada_el')->orderBy('numero')->first();
                    $tarde = $vencidas > 0 && $k % 3 === 0 ? 6 : 0;
                    Calculo::registrarPago($c->fresh(), $letra->vence_el->copy()->addDays($tarde), 1, false, $k % 2 ? 'Transferencia' : 'Efectivo');
                }
            }
            return count($unidades);
        });
    }

    /** Quita unidades, ventas, pagos, fotos y clientes de ejemplo */
    public static function quitar(): int
    {
        $unidades = Unidad::where('ejemplo', true)->with('fotos')->get();
        DB::transaction(function () use ($unidades) {
            foreach ($unidades as $u) {
                foreach ($u->fotos as $f) FotoUnidad::quitar($f);
                Credito::where('unidad_id', $u->id)->delete();
                $u->delete();
            }
            Cliente::where('ejemplo', true)->whereDoesntHave('presupuestos')->delete();
        });
        return $unidades->count();
    }
}
