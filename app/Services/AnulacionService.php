<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\User;
use App\Notifications\AvisoDelSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * VD-VTA-002 · Anular con aprobación (RN-43).
 *
 *   · Quien tiene `ventas.solicitar_anulacion` pide la anulación con motivo.
 *     La venta no cambia hasta que alguien con `ventas.anular` decida.
 *   · Aprobar ejecuta SaleService::anular: reingresa la mercancía, libera
 *     el cupón, resta los puntos y deja el rastro de auditoría. El motivo
 *     que queda en la venta dice quién la pidió.
 *   · Rechazar exige motivo y deja la venta como estaba.
 *   · Una venta admite una sola solicitud pendiente, y mientras la tenga no
 *     se le registran devoluciones (se pisarían: una devuelve parte, la
 *     otra todo).
 *   · Si un supervisor anula la venta directo, las solicitudes pendientes
 *     quedan aprobadas por él (SaleService::anular las cierra).
 */
class AnulacionService
{
    public function __construct(private SaleService $ventas)
    {
    }

    public function solicitar(Sale $venta, User $quien, string $motivo): SaleVoidRequest
    {
        $solicitud = DB::transaction(function () use ($venta, $quien, $motivo) {
            $venta = Sale::whereKey($venta->getKey())->lockForUpdate()->firstOrFail();

            if ($venta->status === Sale::ANULADA) {
                throw ValidationException::withMessages(['venta' => 'Esta venta ya fue anulada.']);
            }

            if ($venta->voidRequests()->pendientes()->exists()) {
                throw ValidationException::withMessages(['venta' => 'Esta venta ya tiene una solicitud de anulación esperando aprobación.']);
            }

            if ($venta->returns()->vigentes()->exists()) {
                throw ValidationException::withMessages([
                    'venta' => 'Esta venta ya tiene devoluciones: no se puede anular. Registra una devolución por lo que falta.',
                ]);
            }

            return SaleVoidRequest::create([
                'tienda_id'    => $venta->tienda_id,
                'sale_id'      => $venta->id,
                'requested_by' => $quien->id,
                'reason'       => $motivo,
            ]);
        });

        $this->avisarAprobadores($solicitud->load('sale'), $quien);

        return $solicitud;
    }

    public function aprobar(SaleVoidRequest $solicitud, User $quien): Sale
    {
        return DB::transaction(function () use ($solicitud, $quien) {
            $solicitud = $this->pendiente($solicitud);
            $nombre    = $solicitud->requester?->nombre_completo ?? 'alguien';

            // anular() también cierra esta solicitud como aprobada por $quien.
            return $this->ventas->anular(
                $solicitud->sale,
                $quien->id,
                $solicitud->reason . ' (solicitada por ' . $nombre . ')',
            );
        });
    }

    public function rechazar(SaleVoidRequest $solicitud, User $quien, string $motivo): SaleVoidRequest
    {
        return DB::transaction(function () use ($solicitud, $quien, $motivo) {
            $solicitud = $this->pendiente($solicitud);

            $solicitud->forceFill([
                'status'           => SaleVoidRequest::RECHAZADA,
                'reviewed_by'      => $quien->id,
                'reviewed_at'      => now(),
                'rejection_reason' => $motivo,
            ])->save();

            return $solicitud;
        });
    }

    private function pendiente(SaleVoidRequest $solicitud): SaleVoidRequest
    {
        $solicitud = SaleVoidRequest::whereKey($solicitud->getKey())->lockForUpdate()->firstOrFail();

        if ($solicitud->status !== SaleVoidRequest::PENDIENTE) {
            throw ValidationException::withMessages(['solicitud' => 'Esta solicitud ya fue revisada.']);
        }

        return $solicitud;
    }

    /** Avisa en la campana a quienes pueden aprobar en ese negocio. */
    private function avisarAprobadores(SaleVoidRequest $solicitud, User $quien): void
    {
        $empresaId = \App\Models\Tienda::withoutGlobalScopes()->whereKey($solicitud->tienda_id)->value('empresa_id');

        $aprobadores = User::query()->activos()->deLaEmpresa($empresaId)->whereKeyNot($quien->id)->get()
            ->filter(function (User $u) {
                try {
                    return $u->hasPermissionTo('ventas.anular');
                } catch (\Throwable) {
                    return false;
                }
            });

        Notification::send($aprobadores, new AvisoDelSistema(
            'Anulación por aprobar: ' . $solicitud->sale->number,
            $quien->nombre_completo . ' pide anular la venta por $' . number_format((float) $solicitud->sale->total, 0, ',', '.')
                . ': «' . $solicitud->reason . '».',
            'warning',
            route('sales.show', $solicitud->sale_id),
            $quien->nombre_completo,
        ));
    }
}
