<?php

namespace App\Http\Controllers;

use App\Models\Tienda;
use App\Support\Contexto;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Cambiar de negocio o de local desde la barra superior.
 *
 * ── La comprobación que importa ──
 *
 * Todo lo que llega aquí viene del navegador, y del navegador se puede
 * mandar cualquier número. Por eso no se guarda lo que pidieron: se guarda
 * lo que se comprobó.
 *
 *   - Que la empresa sea una de las suyas.
 *   - Que la empresa esté al día (ni suspendida ni vencida).
 *   - Que el local sea de ESA empresa y de los que él puede usar.
 *
 * Si algo no pasa, no se cambia nada y se vuelve con un aviso. Nunca se
 * cambia «a medias»: cambiar de empresa y dejar el local anterior sería
 * dejar al sistema apuntando al local de otro cliente.
 */
class ContextoController extends Controller
{
    public function cambiar(Request $request)
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'empresa_id' => ['required', 'integer'],
            'tienda_id'  => ['nullable', 'integer'],
        ]);

        $empresaId = (int) $datos['empresa_id'];

        // Se busca dentro de las suyas, no en toda la tabla: así el propio
        // «no lo encontré» ya es la comprobación de pertenencia.
        $empresa = $usuario->empresas()->whereKey($empresaId)->first();

        if (! $empresa) {
            throw ValidationException::withMessages([
                'empresa_id' => __('mus.contexto.no_es_tuya'),
            ]);
        }

        if (! $empresa->estaActiva()) {
            throw ValidationException::withMessages([
                'empresa_id' => $empresa->haVencido()
                    ? __('mus.contexto.vencida', ['negocio' => $empresa->nombre])
                    : __('mus.contexto.suspendida', ['negocio' => $empresa->nombre]),
            ]);
        }

        $tienda = $this->resolverTienda($usuario, $empresa->id, $datos['tienda_id'] ?? null);

        Contexto::usar($empresa->id, $tienda?->id);

        return back()->with('success', $this->aviso($empresa, $tienda));
    }

    /**
     * El local con el que se queda.
     *
     * Si el que pidieron no sirve —no es de esta empresa, está inactivo, o
     * simplemente no mandaron ninguno porque estaban cambiando de negocio—
     * se toma el primero que sí puede usar. Quedarse sin local no es una
     * opción: sin local no se puede vender.
     */
    private function resolverTienda($usuario, int $empresaId, ?int $pedida): ?Tienda
    {
        $disponibles = $usuario->tiendasEn($empresaId);

        if ($disponibles->isEmpty()) {
            return null;
        }

        return $disponibles->firstWhere('id', $pedida) ?: $disponibles->first();
    }

    private function aviso($empresa, ?Tienda $tienda): string
    {
        if ($tienda) {
            return __('mus.contexto.ahora_en', [
                'lugar' => $tienda->nombreCompleto(),
            ]);
        }

        return __('mus.contexto.ahora_en', ['lugar' => $empresa->nombre]);
    }
}
