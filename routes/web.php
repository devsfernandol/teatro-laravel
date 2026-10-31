<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CarteleraController;
use App\Http\Controllers\EntradaController;
use App\Http\Controllers\FuncionController;
use App\Http\Controllers\OrdenController;
use App\Http\Controllers\WebhookPayPalController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:global')->group(function () {

    // --- Sitio público -----------------------------------------------------
    Route::get('/', [CarteleraController::class, 'index'])->name('cartelera');
    Route::get('/obras/{obra:slug}', [CarteleraController::class, 'obra'])->name('obras.show');

    Route::get('/funciones/{funcion}/asientos', [FuncionController::class, 'asientos'])
        ->whereNumber('funcion')->name('funciones.asientos');
    Route::post('/funciones/{funcion}/reservar', [FuncionController::class, 'reservar'])
        ->whereNumber('funcion')->middleware('throttle:reservas')->name('funciones.reservar');

    Route::prefix('/ordenes/{orden:uuid}')->whereUuid('orden')->name('ordenes.')->group(function () {
        Route::get('/', [OrdenController::class, 'show'])->name('show');
        Route::middleware('throttle:pagos')->group(function () {
            Route::post('/pagar-demo', [OrdenController::class, 'pagarDemo'])->name('pagar-demo');
            Route::post('/pagar-paypal', [OrdenController::class, 'pagarPayPal'])->name('pagar-paypal');
            Route::get('/paypal/retorno', [OrdenController::class, 'retornoPayPal'])->name('paypal.retorno');
            Route::get('/paypal/cancelado', [OrdenController::class, 'canceladoPayPal'])->name('paypal.cancelado');
            Route::post('/cancelar', [OrdenController::class, 'cancelar'])->name('cancelar');
        });
    });

    Route::get('/entradas/recuperar', [EntradaController::class, 'recuperar'])->name('entradas.recuperar');
    Route::post('/entradas/recuperar', [EntradaController::class, 'enviarRecuperacion'])
        ->middleware('throttle:recuperar')->name('entradas.recuperar.enviar');
    Route::get('/entradas/{codigo}', [EntradaController::class, 'show'])
        ->where('codigo', '[A-Za-z0-9]{16}')->name('entradas.show');

    // --- Panel de administración ------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('guest')->group(function () {
            Route::get('/login', [Admin\AuthController::class, 'create'])->name('login');
            Route::post('/login', [Admin\AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
        });

        Route::middleware(['auth', 'admin'])->group(function () {
            Route::post('/logout', [Admin\AuthController::class, 'destroy'])->name('logout');
            Route::get('/password', [Admin\PasswordController::class, 'edit'])->name('password.edit');
            Route::put('/password', [Admin\PasswordController::class, 'update'])->middleware('throttle:login')->name('password.update');

            Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

            Route::resource('obras', Admin\ObraController::class)->except('show');
            Route::resource('salas', Admin\SalaController::class)->except('show');
            Route::resource('funciones', Admin\FuncionController::class)->parameters(['funciones' => 'funcion']);
            Route::post('funciones/{funcion}/cancelar', [Admin\FuncionController::class, 'cancelar'])->name('funciones.cancelar');

            Route::get('ordenes', [Admin\OrdenController::class, 'index'])->name('ordenes.index');
            Route::get('ordenes/exportar', [Admin\OrdenController::class, 'exportar'])->name('ordenes.exportar');
            Route::get('ordenes/{orden}', [Admin\OrdenController::class, 'show'])->whereNumber('orden')->name('ordenes.show');
            Route::post('ordenes/{orden}/reembolsar', [Admin\OrdenController::class, 'reembolsar'])->whereNumber('orden')->name('ordenes.reembolsar');

            Route::get('usuarios', [Admin\UsuarioController::class, 'index'])->name('usuarios.index');
            Route::get('usuarios/crear', [Admin\UsuarioController::class, 'create'])->name('usuarios.create');
            Route::post('usuarios', [Admin\UsuarioController::class, 'store'])->name('usuarios.store');
            Route::post('usuarios/{usuario}/activo', [Admin\UsuarioController::class, 'alternarActivo'])->name('usuarios.activo');

            Route::get('auditoria', [Admin\AuditoriaController::class, 'index'])->name('auditoria.index');

            Route::prefix('configuracion')->name('configuracion.')->middleware('throttle:configuracion')->group(function () {
                Route::get('/', [Admin\ConfiguracionController::class, 'edit'])->name('edit');
                Route::put('/pagos', [Admin\ConfiguracionController::class, 'actualizarPagos'])->name('pagos');
                Route::post('/pagos/probar', [Admin\ConfiguracionController::class, 'probarPayPal'])->name('pagos.probar');
                Route::put('/correo', [Admin\ConfiguracionController::class, 'actualizarCorreo'])->name('correo');
                Route::post('/correo/probar', [Admin\ConfiguracionController::class, 'probarCorreo'])->name('correo.probar');
            });
        });
    });
});

Route::post('/webhooks/paypal', WebhookPayPalController::class)
    ->middleware('throttle:webhooks')->name('webhooks.paypal');
