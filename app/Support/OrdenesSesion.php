<?php

namespace App\Support;

/**
 * Órdenes creadas en la sesión actual. Las páginas de pago solo se muestran
 * al navegador que hizo la reserva, aunque alguien conozca el UUID.
 */
final class OrdenesSesion
{
    private const CLAVE = 'ordenes';

    private const MAXIMO = 20;

    public static function agregar(string $uuid): void
    {
        $ordenes = session(self::CLAVE, []);
        $ordenes[] = $uuid;
        session([self::CLAVE => array_slice(array_values(array_unique($ordenes)), -self::MAXIMO)]);
    }

    public static function pertenece(string $uuid): bool
    {
        return in_array($uuid, session(self::CLAVE, []), true);
    }
}
