<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falla controlada al leer una factura con IA.
 *
 * Cada código se traduce a un mensaje accionable para el usuario
 * ("toma la foto otra vez" no es lo mismo que "intenta en un minuto").
 */
class InvoiceReadingException extends RuntimeException
{
    public const IMAGE_INVALID      = 'IMAGE_INVALID';
    public const IMAGE_UNREADABLE   = 'IMAGE_UNREADABLE';
    public const NOT_AN_INVOICE     = 'NOT_AN_INVOICE';
    public const NO_ITEMS           = 'NO_ITEMS';
    public const PROVIDER_ERROR     = 'PROVIDER_ERROR';
    public const PROVIDER_TIMEOUT   = 'PROVIDER_TIMEOUT';
    public const INVALID_RESPONSE   = 'INVALID_RESPONSE';
    public const NOT_CONFIGURED     = 'NOT_CONFIGURED';

    private const HTTP_STATUS = [
        self::IMAGE_INVALID    => 422,
        self::IMAGE_UNREADABLE => 422,
        self::NOT_AN_INVOICE   => 422,
        self::NO_ITEMS         => 422,
        self::PROVIDER_ERROR   => 502,
        self::PROVIDER_TIMEOUT => 504,
        self::INVALID_RESPONSE => 502,
        self::NOT_CONFIGURED   => 500,
    ];

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return self::HTTP_STATUS[$this->errorCode] ?? 500;
    }

    public static function unreadable(string $reason): self
    {
        return new self(self::IMAGE_UNREADABLE,
            'No se pudo leer la factura con claridad. Toma la foto de nuevo con buena luz, sin sombras y con la factura completa. Detalle: ' . $reason);
    }
}
