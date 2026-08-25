<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Support\Contexto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra las pruebas vencidas.
 *
 *     php artisan mus:limpiar-demos              (solo dice qué haría)
 *     php artisan mus:limpiar-demos --hacerlo
 *     php artisan mus:limpiar-demos --horas=48 --hacerlo
 *
 * ── Por qué no borra a la hora de vencer ──
 *
 * Porque el interesado que probó a las 3 y volvió a las 7 tiene que
 * encontrar algo. Lo que ve es «tu prueba se venció, escríbenos» —lo hace
 * el guardián de contexto, no este comando— y esa pantalla es la
 * conversación comercial. Si los datos ya no existieran, tampoco existiría
 * la conversación.
 *
 * Por omisión se esperan 72 horas: tres días es tiempo de sobra para
 * devolver la llamada, y una demostración de tres días de antigüedad ya no
 * le sirve a nadie.
 *
 * ── Por qué hay que pedirlo con `--hacerlo` ──
 *
 * Porque esto borra datos y no se puede deshacer. Un comando destructivo
 * que se ejecuta con solo escribir su nombre es un accidente esperando su
 * turno — sobre todo si algún día alguien lo programa mal en el cron.
 */
class LimpiarDemos extends Command
{
    protected $signature = 'mus:limpiar-demos
                            {--horas=72 : Cuántas horas se esperan después de que venza}
                            {--hacerlo : Borrar de verdad. Sin esto solo se informa}';

    protected $description = 'Borra las empresas de demostración que ya vencieron';

    public function handle(): int
    {
        $horas  = max(1, (int) $this->option('horas'));
        $limite = now()->subHours($horas);

        // `sinFiltro` no hace falta —Empresa no lleva el filtro de
        // aislamiento— pero sí olvidar el contexto: si algo lo dejó puesto,
        // el catálogo que se va a contar saldría filtrado por otra empresa.
        Contexto::olvidar();

        $vencidas = Empresa::query()
            ->where('es_demo', true)
            ->whereNotNull('expira_en')
            ->where('expira_en', '<', $limite)
            ->orderBy('expira_en')
            ->get();

        $this->newLine();

        if ($vencidas->isEmpty()) {
            $this->components->info("No hay demostraciones vencidas hace más de {$horas} h.");
            $this->newLine();

            return self::SUCCESS;
        }

        $this->components->info($vencidas->count() . " demostración(es) vencida(s) hace más de {$horas} h");
        $this->newLine();

        foreach ($vencidas as $empresa) {
            $this->line(sprintf(
                '  #%d  %-34s  venció %s  ·  %s',
                $empresa->id,
                mb_strimwidth($empresa->nombre, 0, 34, '…'),
                $empresa->expira_en->diffForHumans(),
                $empresa->correo ?: 'sin correo'
            ));
        }

        $this->newLine();

        if (! $this->option('hacerlo')) {
            $this->components->warn('No se borró nada. Agrega --hacerlo para que se borren.');
            $this->newLine();

            return self::SUCCESS;
        }

        $borradas = 0;

        foreach ($vencidas as $empresa) {
            DB::transaction(function () use ($empresa, &$borradas) {
                $this->borrar($empresa);
                $borradas++;
            });
        }

        $this->components->info("{$borradas} demostración(es) borrada(s).");
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Borra una empresa con todo lo suyo.
     *
     * ── Por qué a mano y no con `delete()` ──
     *
     * Porque `Empresa` usa borrado suave: `delete()` solo le pondría una
     * fecha y los datos seguirían ocupando la base — que es exactamente lo
     * que este comando existe para evitar.
     *
     * Y el orden importa: de los hijos hacia los padres. Al revés, la
     * primera llave foránea corta la operación.
     */
    private function borrar(Empresa $empresa): void
    {
        $tiendas = DB::table('tiendas')->where('empresa_id', $empresa->id)->pluck('id');

        $ventas      = DB::table('sales')->whereIn('tienda_id', $tiendas)->pluck('id');
        $compras     = DB::table('purchases')->whereIn('tienda_id', $tiendas)->pluck('id');
        $devoluciones = DB::table('sale_returns')->whereIn('tienda_id', $tiendas)->pluck('id');

        /* ── Líneas de documento ── */
        DB::table('sale_return_items')->whereIn('sale_return_id', $devoluciones)->delete();
        DB::table('sale_items')->whereIn('sale_id', $ventas)->delete();
        DB::table('sale_payments')->whereIn('sale_id', $ventas)->delete();
        DB::table('purchase_items')->whereIn('purchase_id', $compras)->delete();

        /* ── Documentos ── */
        DB::table('sale_returns')->whereIn('id', $devoluciones)->delete();
        DB::table('sales')->whereIn('id', $ventas)->delete();
        DB::table('purchases')->whereIn('id', $compras)->delete();
        DB::table('stock_movements')->whereIn('tienda_id', $tiendas)->delete();
        DB::table('cash_sessions')->whereIn('tienda_id', $tiendas)->delete();
        DB::table('existencias')->whereIn('tienda_id', $tiendas)->delete();
        DB::table('counters')->whereIn('tienda_id', $tiendas)->delete();

        /* ── Catálogo ── */
        foreach (['products', 'categories', 'units', 'taxes', 'payment_methods',
                  'attributes', 'suppliers', 'customers', 'settings'] as $tabla) {
            DB::table($tabla)->where('empresa_id', $empresa->id)->delete();
        }

        DB::table('audit_logs')->where('empresa_id', $empresa->id)->delete();
        DB::table('mrr_movimientos')->where('empresa_id', $empresa->id)->delete();

        /* ── Gente ──
           Se borran los usuarios que SOLO existían para esta demostración.
           Si alguien probó dos rubros con el mismo correo —cosa que la
           validación no permite hoy, pero podría permitir mañana— su cuenta
           sobrevive mientras le quede al menos un negocio. */
        $usuarios = DB::table('empresa_usuario')->where('empresa_id', $empresa->id)->pluck('user_id');

        DB::table('empresa_usuario')->where('empresa_id', $empresa->id)->delete();
        DB::table('tienda_usuario')->whereIn('tienda_id', $tiendas)->delete();

        foreach ($usuarios as $userId) {
            $leQuedan = DB::table('empresa_usuario')->where('user_id', $userId)->count();

            if ($leQuedan === 0) {
                DB::table('model_has_roles')
                    ->where('model_id', $userId)
                    ->where('model_type', \App\Models\User::class)
                    ->delete();

                DB::table('users')->where('id', $userId)->delete();
            }
        }

        /* ── Los roles propios del rubro ── */
        DB::table('roles')->where('empresa_id', $empresa->id)->delete();

        /* ── Y por fin, el negocio ── */
        DB::table('tiendas')->where('empresa_id', $empresa->id)->delete();
        DB::table('empresas')->where('id', $empresa->id)->delete();
    }
}
