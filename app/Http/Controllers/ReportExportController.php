<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reportes\EnviarExportacionRequest;
use App\Http\Requests\Reportes\GenerarExportacionRequest;
use App\Models\ReportDelivery;
use App\Models\ReportExport;
use App\Services\ExportacionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportaciones de reportes y sus envíos (RP-EXP-001 · TBL-ENV-001).
 */
class ReportExportController extends Controller implements HasMiddleware
{
    public function __construct(private ExportacionService $exportes) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:reportes.exportar', except: ['publico'])];
    }

    /** Historial de archivos generados y de envíos. */
    public function index(Request $request)
    {
        return view('reports.exports', [
            'exportes' => ReportExport::query()
                ->with('user:id,name,last_name')
                ->withCount('deliveries')
                ->latest()
                ->paginate(15, ['*'], 'p')
                ->withQueryString(),
            'envios' => ReportDelivery::query()
                ->with(['export:id,report,format,file_name', 'user:id,name,last_name'])
                ->latest()
                ->take(30)
                ->get(),
            'reportes' => ExportacionService::REPORTES,
            'rango'    => [
                'desde' => $request->query('desde', now()->startOfMonth()->format('Y-m-d')),
                'hasta' => $request->query('hasta', now()->format('Y-m-d')),
            ],
        ]);
    }

    public function store(GenerarExportacionRequest $request)
    {
        $datos = $request->validated();

        try {
            $export = $this->exportes->exportar(
                $datos['report'],
                $datos['format'],
                Carbon::parse($datos['desde'])->startOfDay(),
                Carbon::parse($datos['hasta'])->endOfDay(),
                $request->user()->id,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('reports.exports.index')
            ->with('success', 'Listo: ' . $export->file_name . ' (' . $export->tamano . ', ' . $export->rows . ' filas).')
            ->with('descargar', $export->id);
    }

    public function descargar(ReportExport $export): StreamedResponse
    {
        return $this->archivo($export);
    }

    /** Enlace firmado que va en el mensaje de WhatsApp (vence en 7 días). */
    public function publico(int $export): StreamedResponse
    {
        // La firma ya prueba que el enlace lo generó el sistema. Se busca sin
        // el filtro de negocio: quien lo abre puede no tener sesión, o tenerla
        // en otro negocio.
        return $this->archivo(ReportExport::withoutGlobalScopes()->findOrFail($export));
    }

    public function enviar(EnviarExportacionRequest $request, ReportExport $export)
    {
        $datos = $request->validated();

        try {
            if ($datos['channel'] === ReportDelivery::EMAIL) {
                $this->exportes->enviarPorCorreo($export, $datos['recipient'], $request->user()->id);

                return back()->with('success', 'Envío a ' . $datos['recipient'] . ' registrado. Su estado se actualiza en el historial.');
            }

            ['url' => $url] = $this->exportes->enviarPorWhatsApp($export, $datos['recipient'], $request->user()->id);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('success', 'Envío por WhatsApp registrado.')->with('whatsapp', $url);
    }

    private function archivo(ReportExport $export): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($export->file_path), 404, 'El archivo ya no está disponible.');

        return Storage::disk('local')->download($export->file_path, $export->file_name, ['Content-Type' => $export->mime]);
    }
}
