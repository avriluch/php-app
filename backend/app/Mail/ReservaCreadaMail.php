<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservaCreadaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Booking  $reserva
     * @param  string  $destinatario  'cliente' | 'profesional'
     */
    public function __construct(
        public Booking $reserva,
        public string $destinatario = 'cliente',
    ) {
    }

    public function envelope(): Envelope
    {
        $nombrePro = $this->reserva->professionalProfile?->user?->nombre ?? 'Profesional';

        return new Envelope(
            subject: $this->destinatario === 'profesional'
                ? "Nueva reserva: {$this->reserva->client?->nombre} {$this->reserva->client?->apellido}"
                : "Reserva confirmada con {$nombrePro}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reserva-creada',
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
