<?php

namespace App\Services;

use App\Models\Existencia;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tienda;
use App\Models\User;
use App\Notifications\AvisoDelSistema;
use App\Notifications\StockBajo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Notificaciones persistentes (SA-NOT-001 · RN-37).
 */
class NotificacionService
{
    /**
     * Avisa a quienes ven inventario en ese negocio cuando un movimiento
     * deja el producto en o por debajo de su mínimo. Solo al CRUZAR la
     * línea (no en cada venta posterior) y sin repetir un aviso que la
     * persona todavía no ha leído.
     */
    public function stockBajo(Product $producto, Existencia $existencia, float $saldoAnterior): void
    {
        $minimo = (float) $existencia->min_stock;
        $saldo  = (float) $existencia->stock;

        if ($minimo <= 0 || $saldoAnterior <= $minimo || $saldo > $minimo) {
            return;
        }

        if (! Setting::activo('inventario.alerta_activa')) {
            return;
        }

        $tienda = Tienda::withoutGlobalScopes()->find($existencia->tienda_id);

        if (! $tienda) {
            return;
        }

        $destinatarios = $this->conPermiso($tienda->empresa_id, 'inventario.ver')
            ->reject(fn (User $u) => $u->unreadNotifications()
                ->where('type', StockBajo::class)
                ->get()
                ->contains(fn ($n) => ($n->data['product_id'] ?? null) === $producto->id
                                   && ($n->data['tienda_id'] ?? null) === $tienda->id));

        Notification::send($destinatarios, new StockBajo(
            $producto->id, $producto->name, $saldo, $minimo, $tienda->id, $tienda->nombre
        ));
    }

    /**
     * Aviso manual. El Superadministrador puede mandarlo a todos los
     * negocios; cualquier otro, solo a la gente de su negocio.
     *
     * @return int  cuántas personas lo recibieron
     */
    public function enviarAviso(User $emisor, ?int $empresaId, string $titulo, string $mensaje, string $nivel, ?string $url): int
    {
        $usuarios = User::query()
            ->activos()
            ->when($empresaId !== null, fn ($q) => $q->deLaEmpresa($empresaId))
            ->whereKeyNot($emisor->id)
            ->get();

        Notification::send($usuarios, new AvisoDelSistema($titulo, $mensaje, $nivel, $url, $emisor->nombre_completo));

        return $usuarios->count();
    }

    /** @return Collection<int, User> */
    private function conPermiso(int $empresaId, string $permiso): Collection
    {
        return User::query()
            ->activos()
            ->deLaEmpresa($empresaId)
            ->get()
            ->filter(function (User $u) use ($permiso) {
                try {
                    return $u->hasPermissionTo($permiso);
                } catch (\Throwable) {
                    return false; // permiso sin sembrar: nadie lo tiene
                }
            })
            ->values();
    }
}
