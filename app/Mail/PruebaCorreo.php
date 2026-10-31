<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo de prueba que se envía desde el panel al guardar la configuración SMTP.
 * No se encola: el administrador ve el resultado en el momento.
 */
class PruebaCorreo extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Prueba de correo de '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.prueba');
    }
}
