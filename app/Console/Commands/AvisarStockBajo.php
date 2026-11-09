<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tienda;
use App\Support\Contexto;
use Illuminate\Console\Command;

/**
 * Lista los productos que hay que reponer, local por local.
 *
 *     php artisan mus:stock-bajo
 *     php artisan mus:stock-bajo --tienda=3
 *     php artisan mus:stock-bajo --todos
 *
 * Pensado para programarlo (Programador de tareas de Windows o cron) y
 * revisar de una las reposiciones del día.
 *
 * ── Por qué recorre los locales uno por uno ──
 *
 * Reponer es una pregunta de local, no de catálogo: el mismo tornillo
 * puede estar sobrado en el centro y agotado en el norte. Un listado que
 * mezclara los dos no le serviría a nadie, porque no diría a cuál sede hay
 * que llevar la mercancía.
 *
 * Y como en consola no hay usuario ni sesión, el contexto no se adivina:
 * se pone a mano en cada vuelta con `Contexto::comoEmpresa()`.
 */
class AvisarStockBajo extends Command
{
    protected $signature = 'mus:stock-bajo
                            {--tienda= : Revisar solo este local (id)}
                            {--todos : Incluir también los productos inactivos}';

    protected $description = 'Muestra los productos por debajo de su stock mínimo, local por local';

    public function handle(): int
    {
        $locales = $this->localesARevisar();

        if ($locales->isEmpty()) {
            $this->newLine();
            $this->error('  No hay ningún local que revisar.');
            $this->newLine();

            return self::FAILURE;
        }

        $pendientes = 0;

        foreach ($locales as $local) {
            $pendientes += Contexto::comoEmpresa(
                $local->empresa_id,
                $local->id,
                fn () => $this->revisarLocal($local)
            );
        }

        $this->newLine();

        if ($pendientes === 0) {
            $this->info('  Nada por reponer: todo está por encima de su mínimo.');
        } else {
            $this->warn('  ' . $pendientes . ' producto(s) por reponer en total.');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Tienda> */
    private function localesARevisar()
    {
        $consulta = Tienda::query()->with('empresa:id,nombre')->where('activa', true);

        if ($id = $this->option('tienda')) {
            $consulta->whereKey((int) $id);
        }

        return $consulta->orderBy('empresa_id')
            ->orderByDesc('es_principal')
            ->orderBy('id')
            ->get();
    }

    /** Revisa un local y devuelve cuántos productos hay que reponer ahí. */
    private function revisarLocal(Tienda $local): int
    {
        $consulta = Product::query()
            ->with('category:id,name', 'supplier:id,name')
            ->lowStock()
            ->ordenPorExistencia('asc');

        if (! $this->option('todos')) {
            $consulta->where('is_active', true);
        }

        $productos = $consulta->get();

        if ($productos->isEmpty()) {
            return 0;
        }

        $this->newLine();
        $this->warn('  ' . $local->nombreCompleto() . ' — ' . $productos->count() . ' producto(s) por reponer');
        $this->newLine();

        $this->table(
            ['SKU', 'Producto', 'Stock', 'Mínimo', 'Proveedor'],
            $productos->map(fn (Product $p) => [
                $p->sku ?: '—',
                mb_strimwidth($p->name, 0, 38, '…'),
                (float) $p->stock <= 0 ? '0 (agotado)' : $p->stock_texto,
                $p->min_stock_texto,
                mb_strimwidth($p->supplier->name ?? '—', 0, 28, '…'),
            ])->all()
        );

        $inversion = $productos->sum(
            fn (Product $p) => (float) $p->cost * max(0, (float) $p->min_stock * 2 - (float) $p->stock)
        );

        $this->line('  Reponer al doble del mínimo costaría unos $'
            . number_format($inversion, 0, ',', '.'));

        return $productos->count();
    }
}
