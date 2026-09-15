<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IngresoRegistradoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload
    ) {}

    public function envelope(): Envelope
    {
        $almacen = (string) ($this->payload['almacen'] ?? 'Registro');
        $fecha = (string) ($this->payload['fecha'] ?? '');

        return new Envelope(
            subject: 'Ingreso registrado — '.$almacen.($fecha !== '' ? ' — '.$fecha : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ingreso-registrado',
        );
    }
}
