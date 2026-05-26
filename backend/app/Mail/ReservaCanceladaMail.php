<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservaCanceladaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $reserva,
        public string $destinatario = 'cliente',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reserva cancelada',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reserva-cancelada',
            with: [
                'reserva' => $this->reserva,
                'destinatario' => $this->destinatario,
            ],
        );
    }

    /** @return array<int, \Illuminate\Mail\Mailables\Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
