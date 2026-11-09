<?php

namespace App\Console\Commands;

use App\Services\SesionService;
use Illuminate\Console\Command;

/**
 * TBL-SES-001 · Escenario 3: marca como vencidas (is_active = 0) las
 * sesiones que pasaron su `expires_at`. La validación por petición ya las
 * rechaza; esto deja la tabla al día para el listado y para auditoría.
 */
class ExpirarSesiones extends Command
{
    protected $signature = 'mus:expirar-sesiones';

    protected $description = 'Marca como vencidas las sesiones que pasaron su hora de expiración';

    public function handle(SesionService $sesiones): int
    {
        $n = $sesiones->expirarVencidas();
        $this->info("Sesiones vencidas: {$n}");

        return self::SUCCESS;
    }
}
