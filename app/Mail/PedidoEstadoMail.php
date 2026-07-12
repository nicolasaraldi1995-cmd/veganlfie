<?php

namespace App\Mail;

use App\Models\Pedido;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PedidoEstadoMail extends Mailable
{
    public function __construct(public Pedido $pedido, public string $estadoNuevo) {}

    public function envelope(): Envelope
    {
        $label = Pedido::ESTADOS[$this->estadoNuevo] ?? $this->estadoNuevo;

        return new Envelope(subject: "Pedido #{$this->pedido->id} — {$label}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pedido-estado');
    }
}
