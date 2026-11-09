<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\Tienda;
use App\Services\ReciboEscPos;
use App\Support\Contexto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Diagnóstico RN-28: arma el recibo ESC/POS de una venta y se lo manda al
 * agente de caja SIN pasar por el navegador.
 *
 *   php artisan cajon:probar-recibo          → la última venta
 *   php artisan cajon:probar-recibo 42       → la venta con id 42
 *
 * Si esto imprime y el botón del navegador no, el problema está en el
 * navegador (permiso de red local, caché, consola). Si esto tampoco, está
 * en el agente o en la configuración del local.
 */
class CajonProbarRecibo extends Command
{
    protected $signature = 'cajon:probar-recibo {venta? : Id de la venta; por defecto la última}';

    protected $description = 'Envía el recibo ESC/POS de una venta directo al agente de caja (diagnóstico)';

    public function handle(ReciboEscPos $recibo): int
    {
        $venta = Sale::withoutGlobalScopes()
            ->when($this->argument('venta'), fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->latest('id'))
            ->first();

        if (! $venta) {
            $this->error('No hay ventas para probar.');

            return self::FAILURE;
        }

        $tienda = Tienda::withoutGlobalScopes()->find($venta->tienda_id);

        if ($tienda?->drawer_mode !== Tienda::CAJON_AGENTE) {
            $this->error("El local «{$tienda?->nombre}» no está en modo agente. Corre: php artisan cajon:agente {$tienda?->id}");

            return self::FAILURE;
        }

        // El recibo se arma en el contexto del negocio y local de la venta.
        $bytes = Contexto::comoEmpresa($tienda->empresa_id, $tienda->id, function () use ($recibo, $venta) {
            if (! ReciboEscPos::aplica()) {
                return null;
            }

            return $recibo->generar(Sale::find($venta->id));
        });
        Contexto::olvidar();

        if ($bytes === null) {
            $this->error('Configuración › Recibo está en A4: la térmica solo aplica a papel de 80 o 58 mm.');

            return self::FAILURE;
        }

        $url = 'http://127.0.0.1:' . ($tienda->agent_port ?: 9123);
        $this->line("Venta {$venta->number} · " . strlen($bytes) . " bytes → {$url}/imprimir");

        try {
            $respuesta = Http::timeout(5)
                ->withBody(json_encode(['token' => $tienda->asegurarTokenDeAgente(), 'datos' => base64_encode($bytes)]), 'text/plain')
                ->post("{$url}/imprimir");
        } catch (\Throwable $e) {
            $this->error("El agente no contesta en {$url}: ¿está corriendo? ({$e->getMessage()})");

            return self::FAILURE;
        }

        $this->line('Agente: HTTP ' . $respuesta->status() . ' ' . $respuesta->body());

        if ($respuesta->json('impreso') === true) {
            $this->info('✔ Recibo entregado al agente. Revisa C:\temp\cajon.bin (o la impresora).');

            return self::SUCCESS;
        }

        $this->error('✘ El agente no imprimió. 401 = token distinto al del local (vuelve a correr probar-agente.ps1).');

        return self::FAILURE;
    }
}
