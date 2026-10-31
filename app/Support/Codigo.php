<?php

namespace App\Support;

/**
 * Códigos de entrada: 16 caracteres de un alfabeto sin caracteres ambiguos
 * (sin 0/O ni 1/I/L), unos 80 bits de azar. Imposibles de adivinar.
 */
final class Codigo
{
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generar(int $longitud = 16): string
    {
        $codigo = '';
        $max = strlen(self::ALFABETO) - 1;
        for ($i = 0; $i < $longitud; $i++) {
            $codigo .= self::ALFABETO[random_int(0, $max)];
        }

        return $codigo;
    }

    public static function esValido(mixed $codigo): bool
    {
        return is_string($codigo) && preg_match('/^['.self::ALFABETO.']{16}$/', $codigo) === 1;
    }
}
