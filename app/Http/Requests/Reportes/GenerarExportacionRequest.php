<?php

namespace App\Http\Requests\Reportes;

use App\Models\ReportExport;
use App\Services\ExportacionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Exportar un reporte (RP-EXP-001). */
class GenerarExportacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'report' => ['required', Rule::in(array_keys(ExportacionService::REPORTES))],
            'format' => ['required', Rule::in([ReportExport::PDF, ReportExport::XLSX])],
            'desde'  => ['required', 'date'],
            'hasta'  => ['required', 'date', 'after_or_equal:desde'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'report.in'            => 'Elige un reporte de la lista.',
            'format.in'            => 'El formato debe ser PDF o Excel.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }
}
