<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Archivo PDF/XLSX generado desde los reportes (RP-EXP-001).
 */
class ReportExport extends Model
{
    use PerteneceAEmpresa;

    public const PDF  = 'pdf';
    public const XLSX = 'xlsx';

    protected $fillable = [
        'empresa_id', 'tienda_id', 'user_id', 'report', 'format', 'filters',
        'file_name', 'file_path', 'size_bytes', 'rows',
    ];

    protected function casts(): array
    {
        return ['filters' => 'array', 'size_bytes' => 'integer', 'rows' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ReportDelivery::class)->latest();
    }

    /** «48 KB», «1,2 MB». */
    public function getTamanoAttribute(): string
    {
        $b = $this->size_bytes;

        return match (true) {
            $b >= 1048576 => number_format($b / 1048576, 1, ',', '.') . ' MB',
            $b >= 1024    => number_format($b / 1024, 0, ',', '.') . ' KB',
            default       => $b . ' B',
        };
    }

    public function getMimeAttribute(): string
    {
        return $this->format === self::PDF
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
}
