<?php

namespace App\Support\Fitness\Horarios;

/** Quien mira los datos de horarios y devuelve hallazgos. Hoy: Reglas. Después: un modelo de IA con la misma salida */
interface Analizador
{
    /** @return Hallazgo[] */
    public function hallazgos(Datos $datos): array;

    /** Cómo se describe en pantalla ("análisis automático", "con IA"…) */
    public function nombre(): string;
}
