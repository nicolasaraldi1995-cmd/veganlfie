<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1a1d21; padding: 30px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 25px; border-bottom: 2px solid #2ab5a5; padding-bottom: 15px; }
        .brand { font-size: 20px; font-weight: bold; color: #2ab5a5; }
        .brand small { display: block; font-size: 10px; color: #9a9da5; font-weight: normal; }
        .info { text-align: right; }
        .info p { margin: 2px 0; }
        .section { margin: 20px 0; }
        .section h3 { font-size: 13px; color: #2ab5a5; margin-bottom: 8px; border-bottom: 1px solid #e6e4df; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f0eeea; text-align: left; padding: 8px 10px; font-size: 11px; font-weight: 600; }
        td { padding: 7px 10px; border-bottom: 1px solid #f0eeea; font-size: 11px; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; font-size: 13px; border-top: 2px solid #1a1d21; }
        .badge { display: inline-block; background: #2ab5a5; color: #fff; padding: 2px 10px; border-radius: 10px; font-size: 10px; }
        .footer { margin-top: 30px; text-align: center; color: #9a9da5; font-size: 10px; border-top: 1px solid #e6e4df; padding-top: 10px; }
        .watermark { position: fixed; top: 320px; left: 0; width: 100%; text-align: center; }
        .watermark img { width: 380px; opacity: 0.07; }
    </style>
</head>
<body>
    <div class="watermark">
        <img src="{{ public_path('images/logo.png') }}" alt="">
    </div>

    <div class="header">
        <div>
            <div class="brand">VEGANLIFE<small>Distribuidora Vegana</small></div>
        </div>
        <div class="info">
            <p><strong>Pedido #{{ $pedido->id }}</strong></p>
            <p>{{ $pedido->created_at->format('d/m/Y H:i') }}</p>
            <p><span class="badge">{{ \App\Models\Pedido::ESTADOS[$pedido->estado] ?? $pedido->estado }}</span></p>
        </div>
    </div>

    <div class="section">
        <h3>Datos del cliente</h3>
        <p><strong>{{ $pedido->datos_cliente['nombre'] ?? '' }}</strong></p>
        @if(!empty($pedido->datos_cliente['negocio']))<p>{{ $pedido->datos_cliente['negocio'] }}</p>@endif
        <p>{{ $pedido->datos_cliente['celular'] ?? '' }} · {{ $pedido->datos_cliente['email'] ?? '' }}</p>
        <p>{{ $pedido->datos_cliente['direccion'] ?? '' }}, {{ $pedido->datos_cliente['ciudad'] ?? '' }}</p>
        <p>Entrega: {{ ($pedido->datos_cliente['entrega'] ?? 'envio') === 'retiro' ? 'Retiro en local' : 'Envío a domicilio' }}</p>
    </div>

    <div class="section">
        <h3>Productos</h3>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Marca</th>
                    <th>Presentación</th>
                    <th class="text-right">Cant.</th>
                    <th class="text-right">Precio</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pedido->items as $item)
                <tr>
                    <td>{{ $item->presentacion->producto->nombre ?? 'N/A' }}</td>
                    <td>{{ $item->presentacion->producto->marca->nombre ?? '' }}</td>
                    <td>{{ $item->presentacion->unidad ?? '' }}</td>
                    <td class="text-right">{{ $item->cantidad }}</td>
                    <td class="text-right">${{ number_format($item->precio_unitario, 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($item->subtotal, 2, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="5" class="text-right">Total</td>
                    <td class="text-right">${{ number_format($pedido->total, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if(!empty($pedido->datos_cliente['notas']))
    <div class="section">
        <h3>Notas</h3>
        <p>{{ $pedido->datos_cliente['notas'] }}</p>
    </div>
    @endif

    <div class="footer">
        VEGANLIFE — Distribuidora Vegana · Pergamino, Buenos Aires · veganlife.com.ar
    </div>
</body>
</html>
