<?php

if (! function_exists('dinero')) {
    /**
     * Formatea un importe con su moneda: "12.50 USD".
     */
    function dinero(string|int|float|null $valor, ?string $moneda = null): string
    {
        return number_format((float) $valor, 2, '.', ',').' '.($moneda ?? config('teatro.moneda'));
    }
}

if (! function_exists('fecha_larga')) {
    /**
     * "sábado 7 de noviembre de 2026, 20:00 h".
     */
    function fecha_larga(?DateTimeInterface $fecha): string
    {
        if ($fecha === null) {
            return '';
        }

        return \Illuminate\Support\Carbon::instance($fecha)->locale('es')->translatedFormat('l j \d\e F \d\e Y, H:i \h');
    }
}

if (! function_exists('fecha_corta')) {
    function fecha_corta(?DateTimeInterface $fecha): string
    {
        return $fecha === null ? '' : \Illuminate\Support\Carbon::instance($fecha)->format('d/m/Y H:i');
    }
}
