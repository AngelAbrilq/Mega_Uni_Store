<?php

namespace App\Mail;

use App\Models\PayrollEmployee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** NR-NOM-008 E4 · «Desprendible de nómina [período]» con el PDF adjunto. */
class DesprendibleMail extends Mailable
{
    use Queueable;

    public function __construct(public PayrollEmployee $fila, private string $pdf, private string $archivo) {}

    public function envelope(): Envelope
    {
        $n = $this->fila->payroll;

        return new Envelope(subject: 'Desprendible de nómina ' . $n->period_start->format('d/m/Y') . ' – ' . $n->period_end->format('d/m/Y'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.desprendible', with: ['fila' => $this->fila]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->archivo)->withMime('application/pdf')];
    }
}
