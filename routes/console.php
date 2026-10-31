<?php

use Illuminate\Support\Facades\Schedule;

// Libera los asientos de reservas vencidas y confirma pagos de PayPal pendientes.
Schedule::command('teatro:mantenimiento')->everyMinute()->withoutOverlapping(5);

Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('auth:clear-resets')->daily();
