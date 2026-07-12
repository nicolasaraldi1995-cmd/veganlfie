<div style="font-family:Inter,Arial,sans-serif;max-width:500px;margin:0 auto;padding:30px;background:#f4f2ee;color:#1a1d21;">
    <h2 style="color:#2ab5a5;margin:0 0 20px;">VEGANLIFE</h2>

    <p>Hola {{ $pedido->datos_cliente['nombre'] ?? $pedido->user?->name ?? '' }},</p>

    <p>Tu pedido <strong>#{{ $pedido->id }}</strong> cambió de estado:</p>

    <div style="background:#fff;border-radius:12px;padding:20px;margin:20px 0;border:1px solid rgba(0,0,0,0.08);">
        <p style="margin:0 0 8px;"><strong>Estado:</strong>
            <span style="display:inline-block;background:#2ab5a5;color:#fff;padding:3px 12px;border-radius:8px;font-size:13px;">
                {{ \App\Models\Pedido::ESTADOS[$estadoNuevo] ?? $estadoNuevo }}
            </span>
        </p>
        <p style="margin:0;color:#5a5e66;font-size:14px;">Total: ${{ number_format($pedido->total, 2, ',', '.') }}</p>
    </div>

    @if($estadoNuevo === 'confirmed')
        <p>Tu pedido fue confirmado y pronto lo vamos a preparar.</p>
    @elseif($estadoNuevo === 'preparing')
        <p>Estamos preparando tu pedido.</p>
    @elseif($estadoNuevo === 'shipped')
        <p>Tu pedido ya fue enviado. ¡Pronto lo recibís!</p>
    @elseif($estadoNuevo === 'delivered')
        <p>Tu pedido fue entregado. ¡Gracias por tu compra!</p>
    @elseif($estadoNuevo === 'canceled')
        <p>Tu pedido fue cancelado. Si tenés dudas, escribinos por WhatsApp.</p>
    @endif

    <p style="color:#9a9da5;font-size:12px;margin-top:30px;">VEGANLIFE — Distribuidora Vegana · Pergamino, Buenos Aires</p>
</div>
