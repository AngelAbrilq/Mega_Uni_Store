<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso que un administrador (o la plataforma) envía a los usuarios
 * (SA-NOT-001). Se guarda en `notifications` y aparece en la campana.
 */
class AvisoDelSistema extends Notification
{
    use Queueable;

    public const NIVELES = ['info' => 'Información', 'warning' => 'Advertencia', 'danger' => 'Urgente'];

    public function __construct(
        public string $titulo,
        public string $mensaje,
        public string $nivel = 'info',
        public ?string $url = null,
        public ?string $emisor = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo'    => 'aviso',
            'titulo'  => $this->titulo,
            'mensaje' => $this->mensaje,
            'nivel'   => $this->nivel,
            'url'     => $this->url,
            'emisor'  => $this->emisor,
        ];
    }
}
