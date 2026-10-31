<?php

return [

    // Minutos que un asiento queda retenido mientras el comprador paga.
    'reserva_minutos' => (int) env('TEATRO_RESERVA_MINUTOS', 10),

    // La venta de una función cierra estos minutos antes de que empiece.
    'cierre_venta_minutos' => (int) env('TEATRO_CIERRE_VENTA_MINUTOS', 15),

    'max_asientos_por_orden' => (int) env('TEATRO_MAX_ASIENTOS', 8),

    // Reservas sin pagar que puede tener a la vez una misma IP o un mismo documento.
    // Evita que alguien bloquee la sala reservando asientos que no piensa pagar.
    'max_reservas_pendientes' => (int) env('TEATRO_MAX_RESERVAS_PENDIENTES', 3),

    'moneda' => strtoupper((string) env('TEATRO_MONEDA', 'USD')),

];
