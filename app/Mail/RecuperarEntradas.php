<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecuperarEntradas extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public Collection $ordenes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Enlaces a sus entradas');
    }

    public function content(): Content
    {
        $this->ordenes->loadMissing('funcion.obra');

        return new Content(view: 'mail.recuperar', with: ['ordenes' => $this->ordenes]);
    }
}
