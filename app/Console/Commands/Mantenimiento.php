<?php

namespace App\Console\Commands;

use App\Services\PagoService;
use App\Services\ReservaService;
use Illuminate\Console\Command;

class Mantenimiento extends Command
{
    protected $signature = 'teatro:mantenimiento';

    protected $description = 'Libera reservas vencidas y confirma pagos de PayPal pendientes';

    public function handle(ReservaService $reservas, PagoService $pagos): int
    {
        $liberadas = $reservas->liberarVencidas();
        $confirmadas = $pagos->reconciliar();

        if ($liberadas > 0 || $confirmadas > 0) {
            $this->info("Reservas liberadas: {$liberadas}. Pagos resueltos: {$confirmadas}.");
        }

        return self::SUCCESS;
    }
}
