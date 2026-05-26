<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservaRecordatorioMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $reserva)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio: tu reserva es mañana',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reserva-recordatorio',
            with: ['reserva' => $this->reserva],
        );
    }

    /** @return array<int, \Illuminate\Mail\Mailables\Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
