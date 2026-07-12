@php
    $record = $getRecord();
    $record->load(['items.presentacion.producto.marca']);
    $isAdmin = auth()->user()?->isAdmin();
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-2.5 text-left font-medium text-gray-500">Producto</th>
                <th class="px-4 py-2.5 text-left font-medium text-gray-500">Marca</th>
                <th class="px-4 py-2.5 text-left font-medium text-gray-500">Presentación</th>
                <th class="px-4 py-2.5 text-right font-medium text-gray-500">Cant.</th>
                @if($isAdmin)
                    <th class="px-4 py-2.5 text-right font-medium text-gray-500">Precio</th>
                    <th class="px-4 py-2.5 text-right font-medium text-gray-500">Subtotal</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($record->items as $item)
                <tr>
                    <td class="px-4 py-2.5 font-medium">{{ $item->presentacion->producto->nombre ?? 'N/A' }}</td>
                    <td class="px-4 py-2.5 text-gray-500">{{ $item->presentacion->producto->marca->nombre ?? '—' }}</td>
                    <td class="px-4 py-2.5">{{ $item->presentacion->unidad ?? '' }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold">{{ $item->cantidad }}</td>
                    @if($isAdmin)
                        <td class="px-4 py-2.5 text-right">${{ number_format($item->precio_unitario, 2, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold">${{ number_format($item->subtotal, 2, ',', '.') }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        @if($isAdmin)
            <tfoot>
                <tr class="bg-gray-50 dark:bg-gray-800">
                    <td colspan="5" class="px-4 py-3 text-right font-semibold">Total</td>
                    <td class="px-4 py-3 text-right text-lg font-bold">${{ number_format($record->total, 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
