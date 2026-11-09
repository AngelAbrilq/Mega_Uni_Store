<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ventas\RechazarPagoRequest;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Pagos por verificar (TBL-PAG-001 · RN-32).
 *
 * Transferencias y similares quedan pendientes en el POS. Aquí un
 * supervisor confirma que la plata llegó o la rechaza; un rechazo deja a
 * la venta con saldo por cobrar.
 */
class SalePaymentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:ventas.verificar_pagos')];
    }

    public function index(Request $request)
    {
        $estado = in_array($request->query('estado'), [SalePayment::PENDIENTE, SalePayment::RECHAZADO], true)
            ? $request->query('estado')
            : SalePayment::PENDIENTE;

        $pagos = SalePayment::query()
            ->where('sale_payments.status', $estado)
            ->whereHas('sale') // respeta el ámbito del local (global scope de Sale)
            ->with(['sale:id,number,tienda_id,total,change_amount,sold_at,user_id', 'sale.user:id,name,last_name', 'verifier:id,name,last_name'])
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('payments.index', compact('pagos', 'estado'));
    }

    public function aprobar(Request $request, SalePayment $payment)
    {
        Gate::authorize('verificar', $payment);

        $this->revisar($payment, SalePayment::APROBADO, $request->user()->id);

        return back()->with('success', 'Pago de $' . number_format((float) $payment->amount, 0, ',', '.')
            . ' (' . $payment->method_name . ') aprobado.');
    }

    public function rechazar(RechazarPagoRequest $request, SalePayment $payment)
    {
        $this->revisar($payment, SalePayment::RECHAZADO, $request->user()->id, $request->validated('rejection_reason'));

        $saldo = $payment->sale->fresh('payments')->saldo_pendiente;

        return back()->with('success', 'Pago rechazado. La venta ' . $payment->sale->number
            . ' queda con saldo por cobrar de $' . number_format($saldo, 0, ',', '.') . '.');
    }

    /**
     * Solo un pago pendiente se revisa, y una sola vez: el estado se relee
     * con bloqueo para que dos clics no lo revisen dos veces (como H-F09).
     */
    private function revisar(SalePayment $payment, string $estado, int $userId, ?string $motivo = null): void
    {
        DB::transaction(function () use ($payment, $estado, $userId, $motivo) {
            $pago = SalePayment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($pago->status !== SalePayment::PENDIENTE) {
                throw ValidationException::withMessages(['pago' => 'Este pago ya fue revisado.']);
            }

            $pago->forceFill([
                'status'           => $estado,
                'verified_by'      => $userId,
                'verified_at'      => now(),
                'rejection_reason' => $motivo,
            ])->save();
        });
    }
}
