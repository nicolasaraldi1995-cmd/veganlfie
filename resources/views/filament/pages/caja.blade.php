<x-filament-panels::page>
    <x-filament::section heading="Período" description="Elegí el rango de fechas para armar la caja.">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button wire:click="generar" icon="heroicon-o-calculator">
                Generar caja
            </x-filament::button>
        </div>
    </x-filament::section>

    @if($showResumen)
        <x-filament::section>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-400">Total en caja</p>
                    <p class="text-xs text-gray-400">Efectivo + transferencia, del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>
                </div>
                <p class="text-3xl font-bold text-green-600">${{ number_format($resumen['totalCaja'], 0, ',', '.') }}</p>
            </div>
        </x-filament::section>

        <x-filament::section heading="Por método de pago" description="MercadoPago y otros medios liquidan aparte: se muestran acá para no perderlos de vista, pero no suman al total de caja.">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Método</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500">Cantidad</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($resumen['porMetodo'] as $metodo)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $metodo['nombre'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $metodo['cantidad'] }}</td>
                                <td class="px-3 py-2 text-right font-semibold">${{ number_format($metodo['total'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">
                                    @unless($metodo['incluido'])
                                        <span class="text-xs text-gray-400">no incluido en caja</span>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Detalle de pagos" description="Todos los pagos del período, ordenados por fecha.">
            @if(count($resumen['detalle']) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Fecha</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Cliente</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Método</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Monto</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Notas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($resumen['detalle'] as $pago)
                                <tr>
                                    <td class="px-3 py-2">{{ $pago['fecha'] }}</td>
                                    <td class="px-3 py-2">{{ $pago['cliente'] ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ $pago['metodo'] === 'Efectivo' ? 'bg-green-100 text-green-700' :
                                               ($pago['metodo'] === 'Transferencia' ? 'bg-blue-100 text-blue-700' :
                                               'bg-gray-100 text-gray-700') }}">
                                            {{ $pago['metodo'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold">${{ number_format($pago['monto'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-gray-400">{{ $pago['notas'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-center text-gray-400 py-8">No hay pagos registrados en este período.</p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
