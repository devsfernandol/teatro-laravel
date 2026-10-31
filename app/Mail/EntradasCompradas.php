<?php

namespace App\Mail;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EntradasCompradas extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900];

    public function __construct(public Orden $orden)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sus entradas para '.$this->orden->funcion->obra->titulo);
    }

    public function content(): Content
    {
        $this->orden->loadMissing('funcion.obra', 'funcion.sala', 'entradas.asiento');

        return new Content(view: 'mail.entradas', with: ['orden' => $this->orden]);
    }
}
