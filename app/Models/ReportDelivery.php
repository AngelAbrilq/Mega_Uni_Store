<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlPadre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Envío de una exportación por email o WhatsApp (TBL-ENV-001).
 */
class ReportDelivery extends Model
{
    use PerteneceAlPadre;

    public const EMAIL    = 'email';
    public const WHATSAPP = 'whatsapp';

    public const PENDIENTE = 'pendiente';
    public const ENVIADO   = 'enviado';
    public const FALLIDO   = 'fallido';

    protected $fillable = ['report_export_id', 'user_id', 'channel', 'recipient', 'status', 'error', 'sent_at'];

    public function documentoPadre(): array
    {
        return [ReportExport::class, 'report_export_id'];
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function export(): BelongsTo
    {
        return $this->belongsTo(ReportExport::class, 'report_export_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
