<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un producto cruzó su stock mínimo en un local (SA-NOT-001 · RN-37).
 * La crea StockService en el movimiento que lo deja por debajo.
 */
class StockBajo extends Notification
{
    use Queueable;

    public function __construct(
        public int $productId,
        public string $producto,
        public float $saldo,
        public float $minimo,
        public int $tiendaId,
        public string $tienda,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $saldo = rtrim(rtrim(number_format($this->saldo, 3, ',', '.'), '0'), ',');

        return [
            'tipo'       => 'stock_bajo',
            'titulo'     => $this->saldo <= 0 ? "Se agotó {$this->producto}" : "Stock bajo: {$this->producto}",
            'mensaje'    => "Quedan {$saldo} en {$this->tienda} (mínimo " . rtrim(rtrim(number_format($this->minimo, 3, ',', '.'), '0'), ',') . ').',
            'nivel'      => $this->saldo <= 0 ? 'danger' : 'warning',
            'url'        => route('inventory.kardex', $this->productId, false),
            'product_id' => $this->productId,
            'tienda_id'  => $this->tiendaId,
        ];
    }
}
