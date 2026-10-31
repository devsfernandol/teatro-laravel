<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema del teatro.
 *
 * Las reglas críticas las garantiza la base de datos, no solo el código:
 *  - Un asiento no puede venderse ni reservarse dos veces para la misma función
 *    (índice único funcion_id + asiento_id en "entradas").
 *  - No se puede borrar una obra, sala o función que tenga ventas (claves foráneas RESTRICT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->unsignedTinyInteger('filas');
            $table->unsignedTinyInteger('columnas');
            $table->timestamps();
        });

        Schema::create('asientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sala_id')->constrained('salas')->cascadeOnDelete();
            $table->string('fila', 2);
            $table->unsignedTinyInteger('numero');
            $table->boolean('activo')->default(true);
            $table->unique(['sala_id', 'fila', 'numero']);
        });

        Schema::create('obras', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->string('slug', 170)->unique();
            $table->text('sinopsis');
            $table->unsignedSmallInteger('duracion_minutos');
            $table->string('clasificacion', 20)->nullable();
            $table->string('imagen', 120)->nullable();
            $table->boolean('publicada')->default(true);
            $table->timestamps();
        });

        Schema::create('funciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();
            $table->foreignId('sala_id')->constrained('salas')->restrictOnDelete();
            $table->dateTime('inicia_en');
            $table->decimal('precio', 10, 2);
            $table->string('estado', 20)->default('programada');
            $table->timestamps();
            $table->index(['estado', 'inicia_en']);
            $table->index(['sala_id', 'inicia_en']);
        });

        Schema::create('ordenes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('funcion_id')->constrained('funciones')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->string('documento', 20)->index();
            $table->string('email', 190)->index();
            $table->decimal('total', 10, 2);
            $table->char('moneda', 3);
            $table->string('estado', 20);
            $table->string('metodo_pago', 10)->nullable();
            $table->string('paypal_order_id', 64)->nullable()->unique();
            $table->string('paypal_capture_id', 64)->nullable()->unique();
            $table->string('codigo', 32)->nullable()->unique();
            $table->dateTime('expira_en');
            $table->dateTime('pagada_en')->nullable();
            $table->dateTime('reembolsada_en')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['estado', 'expira_en']);
            $table->index(['ip', 'estado']);
        });

        // Cada fila es una entrada (su id es el "número de entrada"). Mientras la
        // orden está pendiente la fila retiene el asiento; si la orden vence o se
        // cancela, la fila se borra y el asiento queda libre.
        Schema::create('entradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $table->foreignId('funcion_id')->constrained('funciones')->restrictOnDelete();
            $table->foreignId('asiento_id')->constrained('asientos')->restrictOnDelete();
            $table->decimal('precio', 10, 2);
            $table->timestamps();
            $table->unique(['funcion_id', 'asiento_id']);
        });

        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 60);
            $table->string('descripcion', 255);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE salas ADD CONSTRAINT chk_salas_dimensiones CHECK (filas BETWEEN 1 AND 26 AND columnas BETWEEN 1 AND 40)');
            DB::statement('ALTER TABLE obras ADD CONSTRAINT chk_obras_duracion CHECK (duracion_minutos BETWEEN 1 AND 600)');
            DB::statement("ALTER TABLE funciones ADD CONSTRAINT chk_funciones_precio CHECK (precio > 0)");
            DB::statement("ALTER TABLE funciones ADD CONSTRAINT chk_funciones_estado CHECK (estado IN ('programada','cancelada'))");
            DB::statement("ALTER TABLE ordenes ADD CONSTRAINT chk_ordenes_estado CHECK (estado IN ('pendiente','procesando','pagada','expirada','cancelada','reembolsada'))");
            DB::statement('ALTER TABLE ordenes ADD CONSTRAINT chk_ordenes_total CHECK (total > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
        Schema::dropIfExists('entradas');
        Schema::dropIfExists('ordenes');
        Schema::dropIfExists('funciones');
        Schema::dropIfExists('obras');
        Schema::dropIfExists('asientos');
        Schema::dropIfExists('salas');
    }
};
