<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\OrdenController;
use App\Models\Auditoria;
use App\Models\Entrada;
use App\Models\Funcion;
use App\Models\Obra;
use App\Models\Orden;
use App\Models\Sala;
use App\Models\User;
use App\Services\PagoService;
use App\Services\ReservaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    public function test_el_panel_exige_iniciar_sesion(): void
    {
        foreach (['/admin', '/admin/obras', '/admin/ordenes', '/admin/usuarios', '/admin/auditoria'] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
        $this->post('/admin/obras', ['titulo' => 'X'])->assertRedirect(route('admin.login'));
    }

    public function test_inicio_de_sesion_correcto_regenera_la_sesion_y_audita(): void
    {
        $this->admin();

        $this->post(route('admin.login.store'), ['email' => 'ADMIN@ejemplo.com', 'password' => 'Clave-Segura-123!'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('auditoria', ['accion' => 'login']);
        $this->get(route('admin.dashboard'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_bloquea_la_cuenta_tras_cinco_intentos_fallidos(): void
    {
        $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.store'), ['email' => 'admin@ejemplo.com', 'password' => 'mala'])
                ->assertSessionHasErrors(['email' => 'Correo o contraseña incorrectos.']);
        }

        // Ni siquiera la contraseña correcta entra mientras dura el bloqueo.
        $this->post(route('admin.login.store'), ['email' => 'admin@ejemplo.com', 'password' => 'Clave-Segura-123!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(5, Auditoria::where('accion', 'login_fallido')->count());
    }

    public function test_usuario_desactivado_no_puede_entrar_y_pierde_la_sesion(): void
    {
        $admin = $this->admin(['activo' => false]);

        $this->post(route('admin.login.store'), ['email' => 'admin@ejemplo.com', 'password' => 'Clave-Segura-123!']);
        $this->assertGuest();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_obliga_a_cambiar_la_contrasena_inicial(): void
    {
        $admin = $this->admin(['debe_cambiar_password' => true]);

        $this->actingAs($admin)->get(route('admin.obras.index'))->assertRedirect(route('admin.password.edit'));

        $this->actingAs($admin)->put(route('admin.password.update'), [
            'password_actual' => 'Clave-Segura-123!',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');

        $this->actingAs($admin)->put(route('admin.password.update'), [
            'password_actual' => 'Clave-Segura-123!',
            'password' => 'Otra-Clave-Mas-Larga-456!',
            'password_confirmation' => 'Otra-Clave-Mas-Larga-456!',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertFalse($admin->fresh()->debe_cambiar_password);
        $this->actingAs($admin->fresh())->get(route('admin.obras.index'))->assertOk();
    }

    public function test_crear_obra_con_imagen_recodificada(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.obras.store'), [
            'titulo' => 'Yerma',
            'sinopsis' => 'Una mujer que desea ser madre en un pueblo que la juzga.',
            'duracion_minutos' => 95,
            'clasificacion' => '+12',
            'publicada' => '1',
            'imagen' => UploadedFile::fake()->image('portada.jpg', 800, 600),
        ])->assertRedirect(route('admin.obras.index'));

        $obra = Obra::where('titulo', 'Yerma')->sole();
        $this->assertSame('yerma', $obra->slug);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.webp$/', $obra->imagen);
        Storage::disk('public')->assertExists('obras/'.$obra->imagen);
        $this->assertDatabaseHas('auditoria', ['accion' => 'obra_creada']);
    }

    public function test_rechaza_archivos_que_no_son_imagenes(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.obras.store'), [
            'titulo' => 'Yerma',
            'sinopsis' => 'Una mujer que desea ser madre en un pueblo que la juzga.',
            'duracion_minutos' => 95,
            'imagen' => UploadedFile::fake()->createWithContent('shell.php.jpg', '<?php system($_GET["c"]);'),
        ])->assertSessionHasErrors('imagen');

        $this->assertSame(0, Obra::count());
    }

    public function test_crear_sala_genera_los_asientos_y_permite_desactivarlos(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.salas.store'), ['nombre' => 'Sala Nueva', 'filas' => 3, 'columnas' => 5]);
        $sala = Sala::where('nombre', 'Sala Nueva')->sole();
        $this->assertSame(15, $sala->asientos()->count());

        $activos = $sala->asientos()->where('numero', '>', 1)->pluck('id')->all();
        $this->actingAs($admin)->put(route('admin.salas.update', $sala), [
            'nombre' => 'Sala Nueva', 'filas' => 3, 'columnas' => 5, 'mapa_enviado' => '1', 'activos' => $activos,
        ])->assertRedirect();

        $this->assertSame(12, $sala->asientos()->where('activo', true)->count());
    }

    public function test_programar_funcion_valida_choques_de_sala(): void
    {
        $admin = $this->admin();
        $existente = $this->crearFuncion(['inicia_en' => now()->addDays(5)->setTime(20, 0)]);

        $this->actingAs($admin)->post(route('admin.funciones.store'), [
            'obra_id' => $existente->obra_id,
            'sala_id' => $existente->sala_id,
            'inicia_en' => now()->addDays(5)->setTime(21, 0)->format('Y-m-d\TH:i'),
            'precio' => '10',
        ])->assertSessionHasErrors('inicia_en');

        $this->actingAs($admin)->post(route('admin.funciones.store'), [
            'obra_id' => $existente->obra_id,
            'sala_id' => $existente->sala_id,
            'inicia_en' => now()->addDays(5)->setTime(22, 0)->format('Y-m-d\TH:i'),
            'precio' => '10.5',
        ])->assertRedirect(route('admin.funciones.index'));

        $this->actingAs($admin)->post(route('admin.funciones.store'), [
            'obra_id' => $existente->obra_id,
            'sala_id' => $existente->sala_id,
            'inicia_en' => now()->subDay()->format('Y-m-d\TH:i'),
            'precio' => '-3',
        ])->assertSessionHasErrors(['inicia_en', 'precio']);

        $this->assertSame(2, Funcion::count());
    }

    public function test_reembolso_libera_asientos_y_queda_auditado(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $funcion = $this->crearFuncion();
        $orden = app(ReservaService::class)->reservar($funcion, $this->asientos($funcion, 2), $this->datosComprador(), null);
        app(PagoService::class)->pagarDemo($orden);

        $this->actingAs($admin)->post(route('admin.ordenes.reembolsar', $orden))->assertSessionHasErrors('confirmar');
        $this->actingAs($admin)->post(route('admin.ordenes.reembolsar', $orden), ['confirmar' => '1'])->assertRedirect();

        $this->assertSame(Orden::REEMBOLSADA, $orden->fresh()->estado);
        $this->assertSame(0, Entrada::count());
        $this->assertDatabaseHas('auditoria', ['accion' => 'reembolso']);
    }

    public function test_no_se_elimina_una_obra_con_funciones(): void
    {
        $funcion = $this->crearFuncion();

        $this->actingAs($this->admin())->delete(route('admin.obras.destroy', $funcion->obra))->assertSessionHasErrors('obra');
        $this->assertNotNull($funcion->obra->fresh());
    }

    public function test_un_admin_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.usuarios.activo', $admin))->assertSessionHasErrors('usuario');
        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_nuevo_admin_debe_cambiar_su_contrasena(): void
    {
        $this->actingAs($this->admin())->post(route('admin.usuarios.store'), [
            'name' => 'Segundo',
            'email' => 'segundo@ejemplo.com',
            'password' => 'Clave-Inicial-789!',
            'password_confirmation' => 'Clave-Inicial-789!',
        ])->assertRedirect(route('admin.usuarios.index'));

        $this->assertTrue(User::where('email', 'segundo@ejemplo.com')->sole()->debe_cambiar_password);
    }

    public function test_exportacion_csv_neutraliza_formulas(): void
    {
        $controlador = app(OrdenController::class);

        $this->assertSame("'=HYPERLINK(\"x\")", $controlador->celdaSegura('=HYPERLINK("x")'));
        $this->assertSame("'+1", $controlador->celdaSegura('+1'));
        $this->assertSame('Ana', $controlador->celdaSegura('Ana'));

        $this->actingAs($this->admin())->get(route('admin.ordenes.exportar'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_paginas_del_panel_cargan(): void
    {
        $this->seed();
        $admin = $this->admin();
        $funcion = Funcion::first();
        $orden = Orden::first();

        foreach ([
            route('admin.dashboard'), route('admin.obras.index'), route('admin.obras.create'), route('admin.obras.edit', $funcion->obra),
            route('admin.salas.index'), route('admin.salas.create'), route('admin.salas.edit', $funcion->sala),
            route('admin.funciones.index'), route('admin.funciones.create'), route('admin.funciones.show', $funcion), route('admin.funciones.edit', $funcion),
            route('admin.ordenes.index', ['q' => 'ana', 'estado' => 'pagada']), route('admin.ordenes.show', $orden),
            route('admin.usuarios.index'), route('admin.usuarios.create'), route('admin.auditoria.index'), route('admin.password.edit'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_paginas_publicas_cargan_con_los_datos_de_demostracion(): void
    {
        $this->seed();

        $this->get('/')->assertOk()->assertSee('La casa de Bernarda Alba')->assertDontSee('Luces de bohemia');
        $funcion = Funcion::whereHas('obra', fn ($q) => $q->where('slug', 'bodas-de-sangre'))->first();
        $this->get(route('obras.show', $funcion->obra))->assertOk();
        $this->get(route('funciones.asientos', $funcion))->assertOk()->assertSee('Escenario');
        $this->get(route('entradas.recuperar'))->assertOk();
        $this->get(route('entradas.show', Orden::first()->codigo))->assertOk();
        $this->get(route('admin.login'))->assertOk();
    }
}
