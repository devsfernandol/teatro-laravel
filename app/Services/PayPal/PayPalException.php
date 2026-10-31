<?php

namespace App\Services\PayPal;

use RuntimeException;
use Throwable;

class PayPalException extends RuntimeException
{
    /** Errores con los que el comprador puede reintentar con otro medio de pago. */
    private const RECHAZOS = ['INSTRUMENT_DECLINED', 'PAYER_ACTION_REQUIRED', 'PAYER_CANNOT_PAY', 'TRANSACTION_REFUSED', 'ORDER_NOT_APPROVED'];

    public function __construct(
        string $mensaje,
        public readonly ?int $estadoHttp = null,
        public readonly ?string $problema = null,
        ?Throwable $previa = null,
    ) {
        parent::__construct($mensaje, 0, $previa);
    }

    public function esRechazoDelPago(): bool
    {
        return in_array($this->problema, self::RECHAZOS, true);
    }
}
