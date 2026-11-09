<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TBL-SES-001 · Una sesión del panel: dispositivo, IP, vigencia y cierre.
 * No lleva ámbito de empresa: es de la persona, que puede trabajar en
 * varios negocios con la misma sesión.
 */
class UserSession extends Model
{
    public const CERRADA  = 'logout';
    public const VENCIDA  = 'expired';
    public const REVOCADA = 'revoked';

    public const MOTIVOS = [
        self::CERRADA  => 'Cerró sesión',
        self::VENCIDA  => 'Venció por inactividad',
        self::REVOCADA => 'Cerrada a distancia',
    ];

    protected $fillable = [
        'user_id', 'token_hash', 'ip_address', 'user_agent', 'device', 'device_type',
        'last_activity_at', 'expires_at', 'is_active', 'ended_at', 'end_reason', 'ended_by',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'expires_at'       => 'datetime',
            'ended_at'         => 'datetime',
            'is_active'        => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Activas de verdad: marcadas activas y sin vencer. */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('expires_at', '>', now());
    }

    public function vigente(): bool
    {
        return $this->is_active && $this->expires_at->isFuture();
    }

    public static function hashDe(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }
}
