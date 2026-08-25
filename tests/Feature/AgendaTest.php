<?php

namespace Tests\Feature;

use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Customer;
use App\Models\Empresa;
use App\Models\Horario;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Recurso;
use App\Models\Tienda;
use App\Models\User;
use App\Services\AgendaService;
use App\Support\Contexto;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * La agenda.
 *
 * ── La prueba que decide si esto sirve ──
 *
 * `test_no_deja_dos_citas_encima`. Un recurso no puede tener dos clientes
 * a la misma hora, y eso es lo único que una agenda tiene que garantizar
 * sin excusas: si falla, el estilista tiene dos personas sentadas
 * esperando y alguien se va molesto.
 *
 * Las demás pruebas cuidan lo que rodea a esa: que respete el horario, que
 * respete los bloqueos, y que tocarse en el borde NO cuente como pisarse —
 * porque una agenda que no deja poner una cita justo cuando termina la
 * anterior le hace perder plata al negocio todos los días.
 */
class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Tienda $tienda;
    private Recurso $laura;
    private User $usuario;
    private Carbon $lunes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->empresa = Empresa::create([
            'nombre' => 'Peluquería Estilo', 'slug' => 'estilo-agenda',
            'rubro'  => 'peluqueria', 'estado' => 'activa',
        ]);

        $this->tienda = Tienda::create([
            'empresa_id' => $this->empresa->id, 'nombre' => 'Principal',
            'slug' => 'principal-agenda', 'es_principal' => true, 'activa' => true,
        ]);

        $this->usuario = User::factory()->create();
        $this->usuario->empresas()->sync([$this->empresa->id => ['es_dueno' => true]]);
        $this->usuario->tiendas()->sync([$this->tienda->id]);

        // Un lunes cualquiera, para que el horario sea predecible.
        $this->lunes = Carbon::parse('next monday')->startOfDay();

        Contexto::comoEmpresa($this->empresa->id, $this->tienda->id, function () {
            $this->usuario->syncRoles(['Administrador']);

            $this->laura = Recurso::create([
                'tienda_id' => $this->tienda->id,
                'nombre'    => 'Laura Martínez',
                'tipo'      => 'persona',
                'color'     => '#8B5CF6',
                'activo'    => true,
            ]);

            // Lunes de 9 a 12 y de 2 a 6: jornada partida, como medio país.
            Horario::create(['recurso_id' => $this->laura->id, 'dia_semana' => 1, 'desde' => '09:00', 'hasta' => '12:00']);
            Horario::create(['recurso_id' => $this->laura->id, 'dia_semana' => 1, 'desde' => '14:00', 'hasta' => '18:00']);
        });

        Contexto::olvidar();
    }

    /* ═══════════════ 1. La regla ═══════════════ */

    public function test_agendar_deja_la_cita_con_numero_y_horas(): void
    {
        $cita = $this->trabajando(fn () => $this->agendar('10:00', 60, 'Corte de dama'));

        $this->assertStringStartsWith('A-', $cita->numero);
        $this->assertSame(60, $cita->duracion());
        $this->assertSame('pendiente', $cita->estado);
        $this->assertSame($this->laura->id, $cita->recurso_id);
    }

    /**
     * La prueba que decide si esto sirve.
     */
    public function test_no_deja_dos_citas_encima(): void
    {
        $this->trabajando(function () {
            $this->agendar('10:00', 60, 'Corte');

            $this->expectException(ValidationException::class);

            // Empieza a las 10:30, cuando la otra va a mitad de camino.
            $this->agendar('10:30', 30, 'Otra');
        });
    }

    public function test_el_mensaje_dice_con_que_choca(): void
    {
        $this->trabajando(function () {
            $this->agendar('10:00', 60, 'Corte de dama');

            try {
                $this->agendar('10:30', 30, 'Otra');
                $this->fail('Dejó agendar encima de otra cita.');
            } catch (ValidationException $e) {
                $mensaje = $e->validator->errors()->first('inicio');

                $this->assertStringContainsString('Corte de dama', $mensaje,
                    'El mensaje no dice con qué cita choca, que es lo único que quien está en el mostrador necesita saber.');
            }
        });
    }

    /**
     * Tocarse en el borde NO es pisarse.
     *
     * Una agenda que no deja poner una cita justo cuando termina la
     * anterior le hace perder plata al negocio todos los días.
     */
    public function test_una_cita_puede_empezar_justo_cuando_termina_la_otra(): void
    {
        $this->trabajando(function () {
            $this->agendar('10:00', 60, 'Primera');
            $segunda = $this->agendar('11:00', 60, 'Segunda');

            $this->assertNotNull($segunda->id);
            $this->assertSame(2, Cita::count());
        });
    }

    /** Una cita cancelada libera el cupo. */
    public function test_una_cita_cancelada_deja_libre_la_hora(): void
    {
        $this->trabajando(function () {
            $primera = $this->agendar('10:00', 60, 'Se canceló');

            app(AgendaService::class)->cambiarEstado($primera, 'cancelada');

            $segunda = $this->agendar('10:00', 60, 'La que entró');

            $this->assertNotNull($segunda->id);
        });
    }

    /* ═══════════════ 2. El horario ═══════════════ */

    public function test_no_deja_agendar_fuera_del_horario(): void
    {
        $this->trabajando(function () {
            $this->expectException(ValidationException::class);

            $this->agendar('07:00', 30, 'Muy temprano');
        });
    }

    /**
     * Una cita tiene que caber ENTERA dentro de una franja.
     *
     * Empezar a las 11:40 y durar una hora no cabe en un horario que
     * termina a las 12 — y aceptarlo significaría que alguien se queda
     * trabajando sin saberlo.
     */
    public function test_no_deja_una_cita_que_se_sale_por_el_final(): void
    {
        $this->trabajando(function () {
            $this->expectException(ValidationException::class);

            $this->agendar('11:40', 60, 'Se pasa del cierre');
        });
    }

    public function test_no_deja_agendar_el_dia_que_no_trabaja(): void
    {
        $this->trabajando(function () {
            $martes = $this->lunes->copy()->addDay()->setTime(10, 0);

            $this->expectException(ValidationException::class);

            app(AgendaService::class)->agendar([
                'recurso_id' => $this->laura->id,
                'inicio'     => $martes,
                'minutos'    => 30,
                'titulo'     => 'Martes',
            ], $this->usuario->id);
        });
    }

    /** La jornada partida funciona: la tarde también es horario. */
    public function test_la_segunda_franja_del_dia_tambien_sirve(): void
    {
        $cita = $this->trabajando(fn () => $this->agendar('15:00', 45, 'De tarde'));

        $this->assertNotNull($cita->id);
    }

    /* ═══════════════ 3. Los bloqueos ═══════════════ */

    public function test_no_deja_agendar_sobre_un_bloqueo(): void
    {
        $this->trabajando(function () {
            Bloqueo::create([
                'recurso_id' => $this->laura->id,
                'inicio'     => $this->lunes->copy()->setTime(10, 0),
                'fin'        => $this->lunes->copy()->setTime(11, 0),
                'motivo'     => 'Cita médica',
            ]);

            try {
                $this->agendar('10:15', 30, 'Choca con el bloqueo');
                $this->fail('Dejó agendar encima de un bloqueo.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Cita médica', $e->validator->errors()->first('inicio'));
            }
        });
    }

    /* ═══════════════ 4. Mover ═══════════════ */

    public function test_mover_una_cita_respeta_las_mismas_reglas(): void
    {
        $this->trabajando(function () {
            $this->agendar('10:00', 60, 'Fija');
            $movible = $this->agendar('15:00', 60, 'La que se mueve');

            $this->expectException(ValidationException::class);

            app(AgendaService::class)->mover($movible, $this->lunes->copy()->setTime(10, 30));
        });
    }

    /** Y una cita no choca consigo misma al correrla un poco. */
    public function test_mover_una_cita_cinco_minutos_no_choca_consigo_misma(): void
    {
        $this->trabajando(function () {
            $cita = $this->agendar('10:00', 60, 'Se corre');

            $movida = app(AgendaService::class)->mover($cita, $this->lunes->copy()->setTime(10, 5));

            $this->assertSame('10:05', $movida->inicio->format('H:i'));
        });
    }

    /* ═══════════════ 5. Huecos y ocupación ═══════════════ */

    public function test_los_huecos_descuentan_lo_ya_agendado(): void
    {
        $this->trabajando(function () {
            $this->agendar('10:00', 60, 'Ocupa');

            $huecos = app(AgendaService::class)->huecos($this->laura->fresh()->load('horarios'), $this->lunes);

            $textos = array_map(
                fn ($h) => $h['desde']->format('H:i') . '-' . $h['hasta']->format('H:i'),
                $huecos
            );

            // Mañana: 9–10 y 11–12 (la cita se comió 10–11). Tarde: 14–18.
            $this->assertContains('09:00-10:00', $textos);
            $this->assertContains('11:00-12:00', $textos);
            $this->assertContains('14:00-18:00', $textos);
            $this->assertNotContains('10:00-11:00', $textos, 'Ofreció como libre una hora que está ocupada.');
        });
    }

    public function test_la_ocupacion_es_el_porcentaje_de_la_jornada_vendido(): void
    {
        $this->trabajando(function () {
            // Jornada de 7 h (3 de mañana + 4 de tarde). Con 210 min = 50%.
            $this->agendar('09:00', 180, 'Larga');
            $this->agendar('14:00', 30, 'Corta');

            $this->assertSame(50, app(AgendaService::class)->ocupacion($this->laura->fresh()->load('horarios'), $this->lunes));
        });
    }

    /* ═══════════════ 6. Cobrar ═══════════════ */

    public function test_una_cita_atendida_se_convierte_en_venta(): void
    {
        $this->trabajando(function () {
            $servicio = Product::create([
                'name' => 'Corte de dama', 'price' => 35_000, 'cost' => 0,
                'duracion_minutos' => 45,
            ]);

            $medio = PaymentMethod::create(['name' => 'Efectivo', 'is_active' => true]);
            $cliente = Customer::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);

            $cita = app(AgendaService::class)->agendar([
                'recurso_id'  => $this->laura->id,
                'inicio'      => $this->lunes->copy()->setTime(10, 0),
                'product_id'  => $servicio->id,
                'customer_id' => $cliente->id,
            ], $this->usuario->id);

            $this->assertSame(45, $cita->duracion(), 'La duración no salió del servicio.');
            $this->assertEqualsWithDelta(35_000, (float) $cita->precio, 0.01);

            app(AgendaService::class)->cambiarEstado($cita, 'atendida');

            $venta = app(AgendaService::class)->aVenta($cita->fresh(), $this->usuario->id, $medio->id);

            $this->assertEqualsWithDelta(35_000, (float) $venta->total, 0.01);
            $this->assertSame($venta->id, $cita->fresh()->sale_id);
            $this->assertSame($cliente->id, $venta->customer_id);
        });
    }

    public function test_no_se_cobra_dos_veces_la_misma_cita(): void
    {
        $this->trabajando(function () {
            PaymentMethod::create(['name' => 'Efectivo', 'is_active' => true]);

            $servicio = Product::create([
                'name' => 'Corte', 'price' => 20_000, 'cost' => 0, 'duracion_minutos' => 30,
            ]);

            $cita = app(AgendaService::class)->agendar([
                'recurso_id' => $this->laura->id,
                'inicio'     => $this->lunes->copy()->setTime(10, 0),
                'product_id' => $servicio->id,
            ], $this->usuario->id);

            app(AgendaService::class)->cambiarEstado($cita, 'atendida');
            app(AgendaService::class)->aVenta($cita->fresh(), $this->usuario->id);

            $this->expectException(ValidationException::class);

            app(AgendaService::class)->aVenta($cita->fresh(), $this->usuario->id);
        });
    }

    /**
     * Cobrar sin decir con qué se paga tiene que funcionar.
     *
     * La pantalla de la cita cobra con un botón. Si el servicio exigiera que
     * le mandaran el medio de pago, ese botón nunca habría servido — y el
     * error que salía no lo explicaba: decía «lo recibido ($0) no alcanza»,
     * cuando quien lo leía no había escrito ningún cero en ninguna parte.
     *
     * Esta prueba nació de un rojo de verdad. Se queda para que el día que
     * alguien «simplifique» esa resolución, el rojo vuelva aquí y no en el
     * mostrador de William.
     */
    public function test_se_puede_cobrar_sin_escoger_medio_de_pago(): void
    {
        $this->trabajando(function () {
            $efectivo = PaymentMethod::create(['name' => 'Efectivo', 'is_active' => true]);
            PaymentMethod::create(['name' => 'Nequi', 'is_active' => true]);

            $servicio = Product::create([
                'name' => 'Cepillado', 'price' => 30_000, 'cost' => 0, 'duracion_minutos' => 40,
            ]);

            $cita = app(AgendaService::class)->agendar([
                'recurso_id' => $this->laura->id,
                'inicio'     => $this->lunes->copy()->setTime(10, 0),
                'product_id' => $servicio->id,
            ], $this->usuario->id);

            app(AgendaService::class)->cambiarEstado($cita, 'atendida');

            $venta = app(AgendaService::class)->aVenta($cita->fresh(), $this->usuario->id);

            $this->assertEqualsWithDelta(30_000, (float) $venta->total, 0.01);
            // Y queda pagada: una venta sin pago descuadra la caja del día.
            $this->assertEqualsWithDelta(30_000, (float) $venta->paid_total, 0.01);
            $this->assertSame(1, $venta->payments()->count());
            $this->assertSame($efectivo->id, $venta->payments()->first()->payment_method_id);
        });
    }

    /** Y si el negocio no tiene ninguno, el mensaje dice adónde ir. */
    public function test_sin_medios_de_pago_el_error_explica_que_hacer(): void
    {
        $this->trabajando(function () {
            $servicio = Product::create([
                'name' => 'Corte', 'price' => 20_000, 'cost' => 0, 'duracion_minutos' => 30,
            ]);

            $cita = app(AgendaService::class)->agendar([
                'recurso_id' => $this->laura->id,
                'inicio'     => $this->lunes->copy()->setTime(10, 0),
                'product_id' => $servicio->id,
            ], $this->usuario->id);

            app(AgendaService::class)->cambiarEstado($cita, 'atendida');

            try {
                app(AgendaService::class)->aVenta($cita->fresh(), $this->usuario->id);
                $this->fail('Cobró sin ningún medio de pago configurado.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString(
                    'Medios de pago',
                    implode(' ', $e->validator->errors()->all()),
                    'El error no le dice al usuario adónde ir.'
                );
            }

            $this->assertNull($cita->fresh()->sale_id, 'La cita quedó cobrada sin venta detrás.');
        });
    }

    /* ═══════════════ 7. Aislamiento ═══════════════ */

    public function test_no_se_agenda_a_un_recurso_de_otro_negocio(): void
    {
        $otra = Empresa::create([
            'nombre' => 'Ajena', 'slug' => 'ajena-agenda',
            'rubro' => 'general', 'estado' => 'activa',
        ]);

        $tiendaAjena = Tienda::create([
            'empresa_id' => $otra->id, 'nombre' => 'Principal',
            'slug' => 'principal-ajena-ag', 'es_principal' => true, 'activa' => true,
        ]);

        $ajeno = Contexto::comoEmpresa($otra->id, $tiendaAjena->id, fn () => Recurso::create([
            'tienda_id' => $tiendaAjena->id, 'nombre' => 'De otro', 'tipo' => 'persona',
            'color' => '#000000', 'activo' => true,
        ]));

        Contexto::olvidar();

        $this->trabajando(function () use ($ajeno) {
            $this->expectException(ValidationException::class);

            app(AgendaService::class)->agendar([
                'recurso_id' => $ajeno->id,
                'inicio'     => $this->lunes->copy()->setTime(10, 0),
                'minutos'    => 30,
            ], $this->usuario->id);
        });
    }

    /* ═══════════════ 8. Por la puerta web ═══════════════ */

    public function test_la_pantalla_de_agenda_abre(): void
    {
        $this->trabajando(fn () => $this->agendar('10:00', 60, 'Visible'));

        $this->actingAs($this->usuario)
            ->get('/agenda?dia=' . $this->lunes->toDateString())
            ->assertOk()
            ->assertSee('Visible')
            ->assertSee('Laura');
    }

    public function test_agendar_desde_la_pantalla(): void
    {
        $this->actingAs($this->usuario)
            ->post('/agenda', [
                'recurso_id' => $this->laura->id,
                'inicio'     => $this->lunes->copy()->setTime(11, 0)->format('Y-m-d\TH:i'),
                'minutos'    => 45,
                'titulo'     => 'Desde la pantalla',
            ])
            ->assertRedirect();

        $this->trabajando(fn () => $this->assertSame(1, Cita::count()));
    }

    /* ═══════════════ Apoyo ═══════════════ */

    private function agendar(string $hora, int $minutos, string $titulo): Cita
    {
        [$h, $m] = explode(':', $hora);

        return app(AgendaService::class)->agendar([
            'recurso_id' => $this->laura->id,
            'inicio'     => $this->lunes->copy()->setTime((int) $h, (int) $m),
            'minutos'    => $minutos,
            'titulo'     => $titulo,
        ], $this->usuario->id);
    }

    private function trabajando(callable $fn): mixed
    {
        $resultado = Contexto::comoEmpresa($this->empresa->id, $this->tienda->id, $fn);

        Contexto::olvidar();

        return $resultado;
    }
}
