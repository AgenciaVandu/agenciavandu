<?php

namespace App\Support\Fitness\Horarios;

/**
 * Un hallazgo sobre los horarios del gimnasio: qué se encontró, con qué números y qué conviene hacer.
 * Es la misma forma la produzca el analizador de reglas de hoy o un modelo de IA mañana.
 */
final class Hallazgo
{
    public const TONOS = [
        'oportunidad' => ['icono' => 'bi-rocket-takeoff', 'texto' => 'Oportunidad'],
        'alerta'      => ['icono' => 'bi-exclamation-diamond', 'texto' => 'Atención'],
        'dato'        => ['icono' => 'bi-lightbulb', 'texto' => 'Dato'],
    ];

    /**
     * @param string $tono oportunidad | alerta | dato
     * @param string $titulo frase corta con lo encontrado (admite <b>)
     * @param string $detalle de dónde sale (números)
     * @param array|null $accion ['texto' => ..., 'url' => ...]
     * @param string $confianza alta | media | baja (cuántas semanas de datos lo respaldan)
     * @param int $peso para ordenar: lo más útil primero
     * @param array $celdas celdas del mapa a resaltar: [[dia, hora], ...]
     */
    public function __construct(
        public string $clave,
        public string $tono,
        public string $titulo,
        public string $detalle,
        public ?array $accion = null,
        public string $confianza = 'media',
        public int $peso = 50,
        public array $celdas = [],
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
