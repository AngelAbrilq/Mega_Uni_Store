<?php

namespace App\Jobs;

use App\Mail\ReporteMail;
use App\Models\ReportDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía por correo una exportación y deja el resultado en el envío
 * (TBL-ENV-001): pendiente → enviado, o fallido con el motivo.
 */
class EnviarReporteJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $envioId) {}

    public function handle(): void
    {
        // Sin filtro de negocio: el job corre fuera de una petición.
        $envio = ReportDelivery::sinFiltroEmpresa()->with(['export' => fn ($q) => $q->withoutGlobalScopes()])->find($this->envioId);

        if (! $envio || $envio->status !== ReportDelivery::PENDIENTE) {
            return;
        }

        try {
            Mail::to($envio->recipient)->send(new ReporteMail($envio->export));

            $envio->forceFill(['status' => ReportDelivery::ENVIADO, 'sent_at' => now(), 'error' => null])->save();
        } catch (Throwable $e) {
            Log::warning('Envío de reporte fallido', ['envio' => $envio->id, 'error' => $e->getMessage()]);

            $envio->forceFill(['status' => ReportDelivery::FALLIDO, 'error' => mb_substr($e->getMessage(), 0, 250)])->save();
        }
    }
}
