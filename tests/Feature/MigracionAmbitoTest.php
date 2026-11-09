<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Tienda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * H-08 · La migración 2026_08_23_900004 reconstruye `counters` y `settings`.
 *
 * Lo que se protege:
 *   - Los consecutivos (RN-07) y la configuración nunca quedan solo en memoria.
 *   - El rollback no aplana en silencio varias tiendas/empresas en una.
 */
class MigracionAmbitoTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = '2026_08_23_900004_contadores_y_ajustes_por_ambito.php';

    /**
     * Las migraciones 700001 y 800001 siembran consecutivos y ajustes por
     * defecto, y la 900004 los asigna a la tienda/empresa id=1. Cada prueba
     * arranca con las dos tablas vacías para controlar exactamente el ámbito.
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('counters')->delete();
        DB::table('settings')->delete();
    }

    public function test_el_rollback_se_niega_si_hay_consecutivos_de_varias_tiendas(): void
    {
        // Arrange
        [$centro, $norte] = $this->dosTiendas();
        $this->contador($centro->id, 'ventas', 120);
        $this->contador($norte->id, 'ventas', 45);

        // Act
        try {
            $this->migracion()->down();
            $this->fail('El rollback debió bloquearse: hay consecutivos de dos tiendas.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback bloqueado', $e->getMessage());
        }

        // Assert: nada se tocó
        $this->assertTrue(Schema::hasColumn('counters', 'tienda_id'));
        $this->assertSame(2, DB::table('counters')->count());
        $this->assertFalse(Schema::hasTable('counters_respaldo_h08'), 'No debe quedar respaldo si no se hizo nada.');
    }

    public function test_ida_y_vuelta_con_una_sola_tienda_conserva_los_datos(): void
    {
        // Arrange
        [$centro] = $this->dosTiendas(soloUna: true);
        $this->contador($centro->id, 'ventas', 77);
        DB::table('settings')->insert([
            'empresa_id' => $centro->empresa_id, 'key' => 'negocio.nombre', 'value' => 'Ferretería',
            'group' => 'negocio', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Act: bajar…
        $this->migracion()->down();

        // Assert: forma vieja, mismos datos
        $this->assertFalse(Schema::hasColumn('counters', 'tienda_id'));
        $this->assertSame(77, (int) DB::table('counters')->where('key', 'ventas')->value('value'));
        $this->assertSame('Ferretería', DB::table('settings')->where('key', 'negocio.nombre')->value('value'));

        // Act: …y volver a subir
        $this->migracion()->up();

        // Assert: forma nueva, el consecutivo NO se reinició
        $this->assertTrue(Schema::hasColumn('counters', 'tienda_id'));
        $this->assertSame(77, (int) DB::table('counters')->where('key', 'ventas')->value('value'));
        $this->assertSame('Ferretería', DB::table('settings')->where('key', 'negocio.nombre')->value('value'));
        $this->assertFalse(Schema::hasTable('counters_respaldo_h08'));
        $this->assertFalse(Schema::hasTable('settings_respaldo_h08'));
    }

    public function test_un_respaldo_huerfano_detiene_la_migracion_sin_tocar_nada(): void
    {
        // Arrange: quedó un respaldo de un intento fallido
        [$centro] = $this->dosTiendas(soloUna: true);
        $this->contador($centro->id, 'ventas', 9);
        Schema::create('counters_respaldo_h08', fn ($t) => $t->string('key'));

        // Act + Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('counters_respaldo_h08');

        $this->migracion()->down();
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function migracion(): object
    {
        return require database_path('migrations/' . self::MIGRACION);
    }

    /** @return array<int, Tienda> */
    private function dosTiendas(bool $soloUna = false): array
    {
        $empresa = Empresa::create(['nombre' => 'Ferretería', 'slug' => 'ferre-h08', 'rubro' => 'general', 'estado' => 'activa']);

        $tiendas = [Tienda::create([
            'empresa_id' => $empresa->id, 'nombre' => 'Centro', 'slug' => 'centro-h08', 'es_principal' => true, 'activa' => true,
        ])];

        if (! $soloUna) {
            $tiendas[] = Tienda::create([
                'empresa_id' => $empresa->id, 'nombre' => 'Norte', 'slug' => 'norte-h08', 'es_principal' => false, 'activa' => true,
            ]);
        }

        return $tiendas;
    }

    private function contador(int $tiendaId, string $clave, int $valor): void
    {
        DB::table('counters')->insert([
            'tienda_id' => $tiendaId, 'key' => $clave, 'value' => $valor,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
