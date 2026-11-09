<?php

namespace App\Services\Nomina;

use App\Mail\DesprendibleMail;
use App\Models\Payroll;
use App\Models\PayrollConcept;
use App\Models\PayrollEmployee;
use App\Models\Setting;
use App\Support\CodigoQr;
use App\Support\Exportes\PdfLienzo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

/**
 * NR-NOM-008 · Desprendible de pago.
 *
 *   · Solo de nóminas aprobadas o pagadas (lo que se entrega es definitivo).
 *   · El QR lleva la URL pública /verificar/nomina/{código}: quien lo escanea
 *     ve el período, el empleado y el neto que hay en la base de datos.
 *   · Masivos: un PDF por empleado en un ZIP «desprendible_[periodo]_[empleado].pdf».
 *   · Correo: «Desprendible de nómina [período]» al correo del empleado
 *     (o al de su usuario); quien no tiene correo se informa y se salta.
 */
class DesprendibleService
{
    private const AZUL   = [0.180, 0.431, 0.659]; // #2E6EA8
    private const TINTA  = [0.10, 0.12, 0.16];
    private const SUAVE  = [0.42, 0.45, 0.50];
    private const LINEA  = [0.88, 0.90, 0.93];
    private const FONDO  = [0.96, 0.97, 0.99];

    public function pdf(PayrollEmployee $fila): string
    {
        $this->entregable($fila->payroll);
        $fila->loadMissing('payroll.tienda', 'employee', 'items', 'contribution', 'contract.position', 'department');

        $n = $fila->payroll;
        $e = $fila->employee;
        $negocio = (string) Setting::obtener('negocio.nombre', 'MEGA UNI STORE');
        $nit = (string) Setting::obtener('negocio.nit', '');

        $p = (new PdfLienzo())->nuevaPagina();
        $x0 = 40;
        $x1 = PdfLienzo::ANCHO - 40;

        // ── Encabezado ──
        $p->rect(0, 0, PdfLienzo::ANCHO, 6, self::AZUL)
          ->texto($x0, 44, $negocio, 15, true)
          ->texto($x0, 60, trim(($nit ? "NIT {$nit} · " : '') . ($n->tienda?->name ?? '')), 8.5, false, self::SUAVE)
          ->texto($x1, 42, 'DESPRENDIBLE DE NÓMINA', 10, true, self::AZUL, 'der')
          ->texto($x1, 56, $n->number . ($n->esAjuste() ? ' (ajuste)' : ''), 8.5, false, self::SUAVE, 'der')
          ->texto($x1, 68, 'Período ' . $n->period_start->format('d/m/Y') . ' – ' . $n->period_end->format('d/m/Y'), 8.5, false, self::SUAVE, 'der');

        // ── Datos del empleado ──
        $y = 88;
        $p->rect($x0, $y, $x1 - $x0, 62, self::FONDO);
        $dato = function (float $x, float $y, string $etq, string $val) use ($p) {
            $p->texto($x, $y, mb_strtoupper($etq), 6.5, true, self::SUAVE)->texto($x, $y + 12, $p->recortar($val, 160, 9.5), 9.5, true);
        };
        $dato($x0 + 12, $y + 16, 'Empleado', $e->nombre_completo);
        $dato($x0 + 190, $y + 16, 'Documento', trim(($e->document_type ?: 'CC') . ' ' . $e->document_number));
        $dato($x0 + 360, $y + 16, 'Código', (string) $e->code);
        $dato($x0 + 12, $y + 44, 'Cargo', $fila->contract?->position?->name ?? '—');
        $dato($x0 + 190, $y + 44, 'Salario básico', $this->pesos($fila->base_salary));
        $dato($x0 + 360, $y + 44, 'Días liquidados', (string) $fila->days_worked . ($fila->absence_days ? " ({$fila->absence_days} no rem.)" : ''));

        // ── Devengados | Deducciones ──
        $y = 170;
        $mitad = ($x0 + $x1) / 2;
        $col = function (float $x, float $ancho, string $titulo, $items, float $total) use ($p, $y) {
            $p->texto($x, $y, mb_strtoupper($titulo), 7.5, true, self::AZUL)->linea($x, $y + 6, $x + $ancho, $y + 6, self::LINEA);
            $yy = $y + 20;

            foreach ($items as $i) {
                $cant = $i->quantity !== null ? rtrim(rtrim(number_format((float) $i->quantity, 2, ',', '.'), '0'), ',') : '';
                $p->texto($x, $yy, $p->recortar($i->name, $ancho - 110, 8.5), 8.5)
                  ->texto($x + $ancho - 70, $yy, $cant, 8, false, self::SUAVE, 'der')
                  ->texto($x + $ancho, $yy, $this->pesos($i->amount), 8.5, false, self::TINTA, 'der');
                $yy += 15;
            }

            return [$yy, $total];
        };

        [$y1] = $col($x0, $mitad - $x0 - 14, 'Devengados', $fila->items->where('type', PayrollConcept::DEVENGADO), (float) $fila->earned);
        [$y2] = $col($mitad + 14, $x1 - $mitad - 14, 'Deducciones', $fila->items->where('type', PayrollConcept::DEDUCCION), (float) $fila->deductions);
        $y = max($y1, $y2, 250) + 4;

        $p->linea($x0, $y, $x1, $y, self::LINEA)
          ->texto($x0, $y + 14, 'Total devengado', 8.5, true)->texto($mitad - 14, $y + 14, $this->pesos($fila->earned), 9, true, self::TINTA, 'der')
          ->texto($mitad + 14, $y + 14, 'Total deducciones', 8.5, true)->texto($x1, $y + 14, $this->pesos($fila->deductions), 9, true, self::TINTA, 'der');

        // ── Neto ──
        $y += 30;
        $p->rect($x0, $y, $x1 - $x0, 40, self::AZUL)
          ->texto($x0 + 16, $y + 25, 'NETO A PAGAR', 11, true, [1, 1, 1])
          ->texto($x1 - 16, $y + 26, $this->pesos($fila->net), 16, true, [1, 1, 1], 'der');

        // ── Aportes de la empresa (informativos) ──
        $a = $fila->contribution;
        $y += 62;

        if ($a) {
            $p->texto($x0, $y, 'APORTES DE LA EMPRESA (INFORMATIVO)', 7.5, true, self::SUAVE)->linea($x0, $y + 6, $x1, $y + 6, self::LINEA);
            $pares = [
                ['IBC', $a->ibc . ($a->ibc_capped ? '*' : '')], ['Salud 8,5 %', $a->health_employer], ['Pensión 12 %', $a->pension_employer],
                ['ARL clase ' . $a->arl_class, $a->arl], ['Caja', $a->ccf], ['ICBF', $a->icbf], ['SENA', $a->sena],
            ];
            $ancho = ($x1 - $x0) / count($pares);

            foreach ($pares as $k => [$etq, $val]) {
                $txt = str_ends_with((string) $val, '*') ? $this->pesos(rtrim((string) $val, '*')) . '*' : $this->pesos($val);
                $p->texto($x0 + $k * $ancho, $y + 20, $etq, 7, false, self::SUAVE)->texto($x0 + $k * $ancho, $y + 32, $txt, 8.5, true);
            }

            if ($a->ibc_capped) {
                $p->texto($x0, $y + 48, '* IBC limitado a 25 SMLMV (Ley 797 de 2003, art. 5).', 7, false, self::SUAVE);
            }

            $y += 56;
        }

        // ── Firma y QR ──
        $y = max($y + 30, 600);
        $url = $this->urlVerificacion($fila);
        $p->linea($x0, $y + 50, $x0 + 200, $y + 50, self::TINTA)
          ->texto($x0, $y + 64, 'Firma autorizada · ' . $negocio, 8, true)
          ->texto($x0, $y + 76, 'Aprobada ' . ($n->approved_at?->format('d/m/Y H:i') ?? '') . ($n->approver ? ' por ' . $n->approver->nombre_completo : ''), 7.5, false, self::SUAVE)
          ->linea($x0 + 260, $y + 50, $x0 + 420, $y + 50, self::TINTA)
          ->texto($x0 + 260, $y + 64, 'Recibí conforme', 8, true)
          ->qr($x1 - 92, $y, 92, CodigoQr::matriz($url))
          ->texto($x1 - 46, $y + 104, 'Verificar: ' . $fila->verification_code, 6.5, false, self::SUAVE, 'centro');

        $p->texto($x0, PdfLienzo::ALTO - 28, 'Documento generado por Mega_Uni_Store el ' . now()->format('d/m/Y H:i') . '. Escanea el QR para validar su autenticidad.', 7, false, self::SUAVE);

        return $p->render('Desprendible ' . $n->number . ' ' . $e->nombre_completo);
    }

    public function nombreArchivo(PayrollEmployee $fila): string
    {
        $fila->loadMissing('payroll', 'employee');

        return 'desprendible_' . $fila->payroll->periodoCorto() . '_' . Str::slug($fila->employee->nombre_completo, '_') . '.pdf';
    }

    /** E2 · ZIP con todos los desprendibles. Devuelve la ruta temporal. */
    public function zip(Payroll $nomina): string
    {
        $this->entregable($nomina);

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Falta la extensión zip de PHP.');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'nom') . '.zip';
        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($nomina->employees()->with('employee')->get() as $fila) {
            $fila->setRelation('payroll', $nomina);
            $zip->addFromString($this->nombreArchivo($fila), $this->pdf($fila));
        }

        $zip->close();

        return $ruta;
    }

    /**
     * E4 · Envía cada desprendible al correo del empleado.
     * @return array{enviados: int, sin_correo: array<int, string>}
     */
    public function enviar(Payroll $nomina): array
    {
        $this->entregable($nomina);
        $enviados = 0;
        $sinCorreo = [];

        foreach ($nomina->employees()->with('employee.user')->get() as $fila) {
            $correo = $fila->employee->email ?: $fila->employee->user?->email;

            if (! $correo) {
                $sinCorreo[] = $fila->employee->nombre_completo;
                continue;
            }

            $fila->setRelation('payroll', $nomina);
            Mail::to($correo)->send(new DesprendibleMail($fila, $this->pdf($fila), $this->nombreArchivo($fila)));
            $enviados++;
        }

        return ['enviados' => $enviados, 'sin_correo' => $sinCorreo];
    }

    public function urlVerificacion(PayrollEmployee $fila): string
    {
        return route('nomina.verificar', $fila->verification_code);
    }

    private function entregable(Payroll $nomina): void
    {
        if ($nomina->esBorrador()) {
            throw ValidationException::withMessages(['nomina' => 'Los desprendibles se generan cuando la nómina está aprobada o pagada.']);
        }
    }

    private function pesos(mixed $v): string
    {
        return '$' . number_format((float) $v, 0, ',', '.');
    }
}
