<?php

namespace App\Http\Controllers;

use App\Http\Requests\Comercial\GuardarCuponRequest;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Cupones de descuento del negocio (AT-CUP-001).
 */
class CouponController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:cupones.ver',      only: ['index', 'show']),
            new Middleware('permission:cupones.crear',    only: ['create', 'store']),
            new Middleware('permission:cupones.editar',   only: ['edit', 'update']),
            new Middleware('permission:cupones.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q = Coupon::normalizar((string) $request->query('q', ''));

        $cupones = Coupon::query()
            ->withSum('usos as descontado', 'discount_amount')
            ->when($q !== '', fn ($c) => $c->where('code', 'like', "%{$q}%"))
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('coupons.index', ['cupones' => $cupones, 'q' => $q]);
    }

    public function create()
    {
        return view('coupons.create');
    }

    public function store(GuardarCuponRequest $request)
    {
        $cupon = Coupon::create($this->datos($request));

        return redirect()->route('coupons.show', $cupon)->with('success', 'Cupón ' . $cupon->code . ' creado.');
    }

    public function show(Coupon $coupon)
    {
        $usos = $coupon->usos()
            ->with('sale:id,number,total,sold_at,status,user_id', 'sale.user:id,name,last_name')
            ->latest()
            ->take(25)
            ->get();

        return view('coupons.show', [
            'cupon'      => $coupon,
            'usos'       => $usos,
            'descontado' => (float) $coupon->usos()->sum('discount_amount'),
        ]);
    }

    public function edit(Coupon $coupon)
    {
        return view('coupons.edit', ['cupon' => $coupon]);
    }

    public function update(GuardarCuponRequest $request, Coupon $coupon)
    {
        $coupon->update($this->datos($request));

        return redirect()->route('coupons.show', $coupon)->with('success', 'Cupón ' . $coupon->code . ' actualizado.');
    }

    /** Borrado lógico: las ventas que lo usaron siguen mostrándolo. */
    public function destroy(Coupon $coupon)
    {
        $codigo = $coupon->code;
        $coupon->delete();

        return redirect()->route('coupons.index')->with('success', 'Cupón ' . $codigo . ' eliminado.');
    }

    /** @return array<string, mixed> */
    private function datos(GuardarCuponRequest $request): array
    {
        $datos = $request->validated();
        $datos['is_active'] = $request->boolean('is_active');

        // El tope solo tiene sentido en los porcentuales.
        if ($datos['type'] === Coupon::FIJO) {
            $datos['max_discount'] = null;
        }

        return $datos;
    }
}
