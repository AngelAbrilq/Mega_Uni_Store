<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use App\Support\Dispositivo;
use Illuminate\Http\Request;

/**
 * TBL-SES-001 · Registro y control de las sesiones del panel (RN-41).
 *
 *   1 · Al entrar se crea un registro con el hash del identificador, la IP,
 *       el dispositivo y el vencimiento (ahora + SESSION_LIFETIME).
 *   2 · En cada petición se valida: activa y sin vencer. Si no, sale.
 *   3 · El vencimiento es por inactividad: cada petición lo corre.
 *   4 · Cada dispositivo es un registro aparte.
 *
 * El registro se ata a la sesión de Laravel por su id guardado EN la
 * sesión (`mus.sesion`), no solo por el hash: así sobrevive a un
 * `session()->regenerate()` sin duplicarse.
 */
class SesionService
{
    public const CLAVE = 'mus.sesion';

    /** Cada cuánto se reescribe la última actividad (no en cada clic). */
    private const REFRESCO_SEGUNDOS = 60;

    /** @return UserSession|null la sesión vigente, o null si hay que sacarlo */
    public function controlar(Request $request, User $usuario): ?UserSession
    {
        $hash   = UserSession::hashDe($request->session()->getId());
        $sesion = $this->buscar($request, $usuario, $hash);

        if (! $sesion) {
            return $this->registrar($request, $usuario, $hash);
        }

        if (! $sesion->vigente()) {
            if ($sesion->is_active) {
                $this->cerrar($sesion, UserSession::VENCIDA);
            }

            return null;
        }

        $cambios = [];

        if ($sesion->token_hash !== $hash) {
            $cambios['token_hash'] = $hash; // la sesión se regeneró
        }

        if (! $sesion->last_activity_at || $sesion->last_activity_at->diffInSeconds(now()) >= self::REFRESCO_SEGUNDOS) {
            $cambios['last_activity_at'] = now();
            $cambios['expires_at']       = $this->vencimiento();
            $cambios['ip_address']       = $request->ip();
        }

        if ($cambios) {
            $sesion->forceFill($cambios)->save();
        }

        return $sesion;
    }

    public function registrar(Request $request, User $usuario, ?string $hash = null): UserSession
    {
        [$dispositivo, $tipo] = Dispositivo::describir($request->userAgent());

        $sesion = UserSession::create([
            'user_id'          => $usuario->id,
            'token_hash'       => $hash ?? UserSession::hashDe($request->session()->getId()),
            'ip_address'       => $request->ip(),
            'user_agent'       => mb_substr((string) $request->userAgent(), 0, 500),
            'device'           => $dispositivo,
            'device_type'      => $tipo,
            'last_activity_at' => now(),
            'expires_at'       => $this->vencimiento(),
            'is_active'        => true,
        ]);

        $request->session()->put(self::CLAVE, $sesion->id);

        return $sesion;
    }

    public function cerrar(UserSession $sesion, string $motivo, ?int $porQuien = null): void
    {
        if (! $sesion->is_active) {
            return;
        }

        $sesion->forceFill([
            'is_active'  => false,
            'ended_at'   => now(),
            'end_reason' => $motivo,
            'ended_by'   => $porQuien,
        ])->save();
    }

    /** Cierra todas las de esa persona, menos (opcionalmente) una. */
    public function cerrarTodas(User $usuario, ?int $menos = null, ?int $porQuien = null): int
    {
        return UserSession::query()
            ->where('user_id', $usuario->id)
            ->where('is_active', true)
            ->when($menos, fn ($q) => $q->whereKeyNot($menos))
            ->update([
                'is_active'  => false,
                'ended_at'   => now(),
                'end_reason' => UserSession::REVOCADA,
                'ended_by'   => $porQuien,
                'updated_at' => now(),
            ]);
    }

    /** Marca como vencidas las que ya pasaron su hora. La usa el comando programado. */
    public function expirarVencidas(): int
    {
        return UserSession::query()
            ->where('is_active', true)
            ->where('expires_at', '<=', now())
            ->update([
                'is_active'  => false,
                'ended_at'   => now(),
                'end_reason' => UserSession::VENCIDA,
                'updated_at' => now(),
            ]);
    }

    /** La sesión de esta petición, si ya está registrada. */
    public function actual(Request $request): ?UserSession
    {
        $id = $request->hasSession() ? $request->session()->get(self::CLAVE) : null;

        return $id ? UserSession::find($id) : null;
    }

    private function buscar(Request $request, User $usuario, string $hash): ?UserSession
    {
        $id = $request->session()->get(self::CLAVE);

        $sesion = $id
            ? UserSession::query()->whereKey($id)->where('user_id', $usuario->id)->first()
            : null;

        return $sesion ?? UserSession::query()->where('token_hash', $hash)->where('user_id', $usuario->id)->first();
    }

    private function vencimiento(): \Illuminate\Support\Carbon
    {
        return now()->addMinutes((int) config('session.lifetime', 120));
    }
}
