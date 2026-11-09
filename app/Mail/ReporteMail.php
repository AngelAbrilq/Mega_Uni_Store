<?php

namespace App\Mail;

use App\Models\ReportExport;
use App\Services\ExportacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo con un reporte exportado adjunto (RP-EXP-001).
 */
class ReporteMail extends Mailable
{
    use Queueable;

    public function __construct(public ReportExport $export) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reporte: ' . (ExportacionService::REPORTES[$this->export->report] ?? 'Mega_Uni_Store'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reporte', with: [
            'titulo' => ExportacionService::REPORTES[$this->export->report] ?? 'Reporte',
            'export' => $this->export,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->export->file_path)
                ->as($this->export->file_name)
                ->withMime($this->export->mime),
        ];
    }
}
