<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Se pega a cualquier modelo y registra sus cambios en audit_logs.
 *
 *     use Auditable;
 *
 * Nada más. Los eventos de Eloquent hacen el resto.
 */
trait Auditable
{
    /** Campos que nunca deben quedar guardados en la bitácora. */
    protected array $noAuditar = ['password', 'remember_token', 'updated_at', 'created_at'];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => $m->registrarAuditoria('creado', [], $m->attributesToArray()));

        static::updated(function (Model $m) {
            $cambios = $m->getChanges();
            unset($cambios['updated_at']);

            if (empty($cambios)) {
                return;   // no hubo cambio real
            }

            $antes = [];
            foreach (array_keys($cambios) as $campo) {
                $antes[$campo] = $m->getOriginal($campo);
            }

            $m->registrarAuditoria('actualizado', $antes, $cambios);
        });

        static::deleted(fn (Model $m) => $m->registrarAuditoria('eliminado', $m->attributesToArray(), []));
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /**
     * Texto con el que se identifica el registro en la bitácora.
     * Se puede sobrescribir en el modelo.
     */
    public function etiquetaAuditoria(): string
    {
        foreach (['name', 'number', 'first_name', 'email'] as $campo) {
            if (! empty($this->{$campo})) {
                return (string) $this->{$campo};
            }
        }

        return class_basename($this) . ' #' . $this->getKey();
    }

    protected function registrarAuditoria(string $evento, array $antes, array $despues): void
    {
        // Durante las migraciones o los seeders la tabla puede no existir
        // todavía; nunca se debe romper la operación principal por auditar.
        if (AuditLog::$silencio) {
            return;
        }

        try {
            if (! Schema::hasTable('audit_logs')) {
                return;
            }

            $limpiar = fn (array $v) => array_diff_key($v, array_flip($this->noAuditar));

            $peticion = request();

            AuditLog::create([
                'user_id'         => Auth::id(),
                'user_name'       => Auth::user()?->name,
                'event'           => $evento,
                'auditable_type'  => static::class,
                'auditable_id'    => $this->getKey(),
                'auditable_label' => mb_substr($this->etiquetaAuditoria(), 0, 200),
                'old_values'      => $limpiar($antes) ?: null,
                'new_values'      => $limpiar($despues) ?: null,
                'ip'              => $peticion?->ip(),
                'user_agent'      => mb_substr((string) $peticion?->userAgent(), 0, 255) ?: null,
                'url'             => mb_substr((string) $peticion?->fullUrl(), 0, 255) ?: null,
            ]);
        } catch (\Throwable $e) {
            // Se ignora en silencio: auditar no puede tumbar una venta.
        }
    }
}
