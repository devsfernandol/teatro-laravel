<?php

namespace App\Support;

/**
 * Operaciones con importes en centavos enteros, para no acumular errores de
 * redondeo de los números de coma flotante.
 */
final class Dinero
{
    public static function aCentavos(string|int|float $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    public static function desdeCentavos(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    public static function iguales(string|int|float $a, string|int|float $b): bool
    {
        return self::aCentavos($a) === self::aCentavos($b);
    }
}
