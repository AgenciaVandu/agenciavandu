<?php

namespace App\Support\Facturas;

/**
 * Código QR (modo byte, corrección M, versiones 1 a 15) para la representación impresa del CFDI.
 * Sin dependencias: basado en el estándar ISO/IEC 18004 (mismo algoritmo que la librería de Nayuki).
 */
class Qr
{
    // Por versión (nivel M): [codewords de corrección por bloque, número de bloques]
    private const ECC_M = [1 => [10, 1], [16, 1], [26, 1], [18, 2], [24, 2], [16, 4], [18, 4], [22, 4], [22, 5], [26, 5],
        [30, 5], [22, 8], [22, 9], [24, 9], [24, 10]];

    private const ALINEACION = [1 => [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50],
        [6, 30, 54], [6, 32, 58], [6, 34, 62], [6, 26, 46, 66], [6, 26, 48, 70]];

    private int $v;
    private int $n;
    /** @var array<int, array<int, bool>> */
    private array $m = [];
    /** @var array<int, array<int, bool>> */
    private array $fn = [];

    /** Matriz de módulos (true = negro), sin zona de silencio */
    public static function matriz(string $texto): array
    {
        return (new self)->codificar($texto);
    }

    /** PNG como data URI, listo para <img> en el PDF */
    public static function png(string $texto, int $escala = 8, int $margen = 4): string
    {
        $mat = self::matriz($texto);
        $n = count($mat);
        $lado = ($n + 2 * $margen) * $escala;
        $img = imagecreate($lado, $lado);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        $negro = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $blanco);
        foreach ($mat as $y => $fila) {
            foreach ($fila as $x => $oscuro) {
                if ($oscuro) {
                    $x0 = ($x + $margen) * $escala;
                    $y0 = ($y + $margen) * $escala;
                    imagefilledrectangle($img, $x0, $y0, $x0 + $escala - 1, $y0 + $escala - 1, $negro);
                }
            }
        }
        ob_start();
        imagepng($img);
        imagedestroy($img);
        return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
    }

    private function codificar(string $texto): array
    {
        $bytes = array_values(unpack('C*', $texto) ?: []);
        $len = count($bytes);
        for ($v = 1; $v <= 15; $v++) {
            $capBits = $this->datosCodewords($v) * 8;
            $usados = 4 + ($v < 10 ? 8 : 16) + 8 * $len;
            if ($usados <= $capBits) break;
        }
        if ($v > 15) throw new \InvalidArgumentException('Texto demasiado largo para el QR.');
        $this->v = $v;
        $this->n = 4 * $v + 17;

        // Datos: modo byte, longitud, bytes, terminador y relleno
        $bits = [];
        $agregar = function (int $valor, int $largo) use (&$bits) {
            for ($i = $largo - 1; $i >= 0; $i--) $bits[] = ($valor >> $i) & 1;
        };
        $agregar(0b0100, 4);
        $agregar($len, $v < 10 ? 8 : 16);
        foreach ($bytes as $b) $agregar($b, 8);
        $cap = $this->datosCodewords($v) * 8;
        $agregar(0, min(4, $cap - count($bits)));
        $agregar(0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $cap; $pad ^= 0xEC ^ 0x11) $agregar($pad, 8);
        $datos = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $b = 0;
            for ($j = 0; $j < 8; $j++) $b = ($b << 1) | $bits[$i + $j];
            $datos[] = $b;
        }

        $todo = $this->conCorreccion($datos);

        $this->m = array_fill(0, $this->n, array_fill(0, $this->n, false));
        $this->fn = $this->m;
        $this->patrones();
        $this->colocar($todo);

        // Elegir la máscara con menor penalización
        $mejor = 0;
        $minimo = PHP_INT_MAX;
        $base = $this->m;
        for ($k = 0; $k < 8; $k++) {
            $this->m = $base;
            $this->mascara($k);
            $this->formato($k);
            $p = $this->penalizacion();
            if ($p < $minimo) { $minimo = $p; $mejor = $k; }
        }
        $this->m = $base;
        $this->mascara($mejor);
        $this->formato($mejor);
        return $this->m;
    }

    private function modulosCrudos(int $v): int
    {
        $r = (16 * $v + 128) * $v + 64;
        if ($v >= 2) {
            $a = intdiv($v, 7) + 2;
            $r -= (25 * $a - 10) * $a - 55;
            if ($v >= 7) $r -= 36;
        }
        return $r;
    }

    private function datosCodewords(int $v): int
    {
        [$ecc, $bloques] = self::ECC_M[$v];
        return intdiv($this->modulosCrudos($v), 8) - $ecc * $bloques;
    }

    private function conCorreccion(array $datos): array
    {
        [$ecc, $num] = self::ECC_M[$this->v];
        $crudos = intdiv($this->modulosCrudos($this->v), 8);
        $cortos = $num - $crudos % $num;
        $largoCorto = intdiv($crudos, $num);
        $divisor = $this->rsDivisor($ecc);
        $bloques = [];
        $k = 0;
        for ($i = 0; $i < $num; $i++) {
            $tam = $largoCorto - $ecc + ($i < $cortos ? 0 : 1);
            $dat = array_slice($datos, $k, $tam);
            $k += $tam;
            $c = $this->rsResto($dat, $divisor);
            if ($i < $cortos) $dat[] = 0;
            $bloques[] = array_merge($dat, $c);
        }
        $r = [];
        $largo = count($bloques[0]);
        for ($i = 0; $i < $largo; $i++) {
            foreach ($bloques as $j => $b) {
                if ($i !== $largoCorto - $ecc || $j >= $cortos) $r[] = $b[$i];
            }
        }
        return $r;
    }

    private static function mul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    private function rsDivisor(int $grado): array
    {
        $r = array_fill(0, $grado, 0);
        $r[$grado - 1] = 1;
        $raiz = 1;
        for ($i = 0; $i < $grado; $i++) {
            for ($j = 0; $j < $grado; $j++) {
                $r[$j] = self::mul($r[$j], $raiz);
                if ($j + 1 < $grado) $r[$j] ^= $r[$j + 1];
            }
            $raiz = self::mul($raiz, 0x02);
        }
        return $r;
    }

    private function rsResto(array $datos, array $divisor): array
    {
        $r = array_fill(0, count($divisor), 0);
        foreach ($datos as $b) {
            $f = $b ^ array_shift($r);
            $r[] = 0;
            foreach ($divisor as $i => $d) $r[$i] ^= self::mul($d, $f);
        }
        return $r;
    }

    private function poner(int $x, int $y, bool $oscuro): void
    {
        $this->m[$y][$x] = $oscuro;
        $this->fn[$y][$x] = true;
    }

    private function patrones(): void
    {
        $n = $this->n;
        for ($i = 0; $i < $n; $i++) {
            $this->poner(6, $i, $i % 2 === 0);
            $this->poner($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$n - 4, 3], [3, $n - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx; $y = $cy + $dy;
                    if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) continue;
                    $d = max(abs($dx), abs($dy));
                    $this->poner($x, $y, $d !== 2 && $d !== 4);
                }
            }
        }
        $pos = self::ALINEACION[$this->v];
        $c = count($pos);
        for ($i = 0; $i < $c; $i++) {
            for ($j = 0; $j < $c; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $c - 1) || ($i === $c - 1 && $j === 0)) continue;
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) $this->poner($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                }
            }
        }
        $this->formato(0);   // reserva el área de formato
        if ($this->v >= 7) {
            $r = $this->v;
            for ($i = 0; $i < 12; $i++) $r = ($r << 1) ^ (($r >> 11) * 0x1F25);
            $bits = ($this->v << 12) | $r;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $n - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->poner($a, $b, $bit);
                $this->poner($b, $a, $bit);
            }
        }
    }

    private function formato(int $mascara): void
    {
        $datos = (0 << 3) | $mascara;   // nivel M = 00
        $r = $datos;
        for ($i = 0; $i < 10; $i++) $r = ($r << 1) ^ (($r >> 9) * 0x537);
        $bits = (($datos << 10) | $r) ^ 0x5412;
        $b = fn ($i) => (($bits >> $i) & 1) === 1;
        $n = $this->n;
        for ($i = 0; $i <= 5; $i++) $this->poner(8, $i, $b($i));
        $this->poner(8, 7, $b(6));
        $this->poner(8, 8, $b(7));
        $this->poner(7, 8, $b(8));
        for ($i = 9; $i < 15; $i++) $this->poner(14 - $i, 8, $b($i));
        for ($i = 0; $i < 8; $i++) $this->poner($n - 1 - $i, 8, $b($i));
        for ($i = 8; $i < 15; $i++) $this->poner(8, $n - 15 + $i, $b($i));
        $this->poner(8, $n - 8, true);
    }

    private function colocar(array $codewords): void
    {
        $n = $this->n;
        $total = count($codewords) * 8;
        $i = 0;
        for ($der = $n - 1; $der >= 1; $der -= 2) {
            if ($der === 6) $der = 5;
            for ($vert = 0; $vert < $n; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $der - $j;
                    $arriba = (($der + 1) & 2) === 0;
                    $y = $arriba ? $n - 1 - $vert : $vert;
                    if (! $this->fn[$y][$x] && $i < $total) {
                        $this->m[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function mascara(int $k): void
    {
        for ($y = 0; $y < $this->n; $y++) {
            for ($x = 0; $x < $this->n; $x++) {
                if ($this->fn[$y][$x]) continue;
                $inv = match ($k) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($inv) $this->m[$y][$x] = ! $this->m[$y][$x];
            }
        }
    }

    /** Penalización simplificada (rachas, bloques 2×2 y balance): solo sirve para elegir la máscara */
    private function penalizacion(): int
    {
        $n = $this->n;
        $p = 0;
        $oscuros = 0;
        for ($y = 0; $y < $n; $y++) {
            foreach ([true, false] as $horizontal) {
                $racha = 0;
                $color = null;
                for ($x = 0; $x < $n; $x++) {
                    $c = $horizontal ? $this->m[$y][$x] : $this->m[$x][$y];
                    if ($c === $color) {
                        $racha++;
                        if ($racha === 5) $p += 3; elseif ($racha > 5) $p++;
                    } else { $color = $c; $racha = 1; }
                }
            }
            for ($x = 0; $x < $n; $x++) {
                if ($this->m[$y][$x]) $oscuros++;
                if ($x < $n - 1 && $y < $n - 1) {
                    $c = $this->m[$y][$x];
                    if ($c === $this->m[$y][$x + 1] && $c === $this->m[$y + 1][$x] && $c === $this->m[$y + 1][$x + 1]) $p += 3;
                }
            }
        }
        $p += intdiv(abs($oscuros * 20 - $n * $n * 10), $n * $n) * 10;
        return $p;
    }
}
