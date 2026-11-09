<?php

namespace App\Console\Commands;

use App\Models\Tienda;
use Illuminate\Console\Command;

/**
 * Deja un local en modo «agente de caja» y muestra su token (RN-28).
 *
 *   php artisan cajon:agente              → el local principal del primer negocio
 *   php artisan cajon:agente 3            → el local con id 3
 *   php artisan cajon:agente 3 --puerto=9200
 *
 * La última línea es «TOKEN=…» para que un script la pueda leer.
 */
class CajonAgente extends Command
{
    protected $signature = 'cajon:agente
                            {tienda? : Id del local; por defecto, el principal del primer negocio}
                            {--puerto=9123 : Puerto del agente en el PC de caja}';

    protected $description = 'Pone un local en modo agente de caja y muestra el token para config.json';

    public function handle(): int
    {
        $consulta = Tienda::withoutGlobalScopes();

        $tienda = $this->argument('tienda')
            ? $consulta->find((int) $this->argument('tienda'))
            : $consulta->where('es_principal', true)->orderBy('empresa_id')->orderBy('id')->first();

        if (! $tienda) {
            $this->error('No encontré ese local. Mira los ids en la tabla «tiendas».');

            return self::FAILURE;
        }

        $puerto = (int) $this->option('puerto');

        if ($puerto < 1024 || $puerto > 65535) {
            $this->error('El puerto debe estar entre 1024 y 65535.');

            return self::FAILURE;
        }

        $tienda->update(['drawer_mode' => Tienda::CAJON_AGENTE, 'agent_port' => $puerto]);

        $this->info("Local «{$tienda->nombre}» (id {$tienda->id}) en modo agente, puerto {$puerto}.");
        $this->line('TOKEN=' . $tienda->asegurarTokenDeAgente());

        return self::SUCCESS;
    }
}
