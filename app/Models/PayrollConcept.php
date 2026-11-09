<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * NR-NOM-002 · Concepto de nómina de la empresa.
 *
 *   calculation = sistema      lo calcula NominaService (salario, auxilio, salud…)
 *               = fijo         valor en pesos
 *               = pct_salario  % del salario básico del período
 *               = pct_smlmv    % del SMLMV («SMLV * 0.117»), prorrateado por días
 *               = manual       el valor se digita por empleado en el borrador
 *
 * Obligatorio = se aplica solo a todos los empleados en cada liquidación.
 * No obligatorio = se agrega a mano a quien corresponda (bonificación…).
 * Desactivar lo saca de nuevas liquidaciones; las nóminas viejas guardan
 * su código y nombre en payroll_items, así que no se pierde nada.
 */
class PayrollConcept extends Model
{
    use Auditable, PerteneceAEmpresa;

    public const DEVENGADO = 'devengado', DEDUCCION = 'deduccion', APORTE = 'aporte';

    public const TIPOS = [self::DEVENGADO => 'Devengado', self::DEDUCCION => 'Deducción', self::APORTE => 'Aporte patronal'];

    public const CALCULOS = [
        'sistema'     => 'Calculado por el sistema',
        'fijo'        => 'Valor fijo',
        'pct_salario' => '% del salario',
        'pct_smlmv'   => '% del SMLMV',
        'manual'      => 'Manual por empleado',
    ];

    /** Los que el sistema crea y calcula; su código no se cambia. */
    public const DEL_SISTEMA = [
        ['SALARIO',  'Salario básico',             self::DEVENGADO, true,  true],
        ['VACACION', 'Vacaciones disfrutadas',     self::DEVENGADO, true,  true],
        ['AUXTRANS', 'Auxilio de transporte',      self::DEVENGADO, true,  false],
        ['HEXTRA',   'Horas extra y recargos',     self::DEVENGADO, true,  true],
        ['SALUD',    'Salud (4 %)',                self::DEDUCCION, true,  false],
        ['PENSION',  'Pensión (4 %)',              self::DEDUCCION, true,  false],
        ['FSP',      'Fondo de solidaridad pensional', self::DEDUCCION, true, false],
        ['AUSENCIA', 'Ausencias no remuneradas',   self::DEDUCCION, true,  false],
        ['ARL',      'ARL (según riesgo)',         self::APORTE,    true,  false],
        ['CAJA',     'Caja de compensación (4 %)', self::APORTE,    true,  false],
    ];

    protected $fillable = ['empresa_id', 'code', 'name', 'type', 'calculation', 'value', 'is_mandatory', 'is_salary', 'is_system', 'is_active'];

    protected $attributes = ['is_active' => true, 'is_system' => false, 'is_mandatory' => false, 'is_salary' => true, 'value' => 0];

    protected function casts(): array
    {
        return ['value' => 'decimal:4', 'is_mandatory' => 'boolean', 'is_salary' => 'boolean', 'is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeActivos(Builder $q): void
    {
        $q->where('is_active', true);
    }

    public function esPorcentaje(): bool
    {
        return in_array($this->calculation, ['pct_salario', 'pct_smlmv'], true);
    }

    public function formula(): string
    {
        $v = rtrim(rtrim(number_format((float) $this->value, 4, ',', '.'), '0'), ',');

        return match ($this->calculation) {
            'fijo'        => '$' . number_format((float) $this->value, 0, ',', '.'),
            'pct_salario' => "Salario × {$v} %",
            'pct_smlmv'   => "SMLMV × {$v} %",
            'manual'      => 'Se digita por empleado',
            default       => 'Automático',
        };
    }
}
