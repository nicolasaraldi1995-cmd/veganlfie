<x-filament-panels::page>
    <x-filament::section
        heading="Pedido que armó el cliente"
        description="Subí el archivo que te mandó por WhatsApp desde la lista de precios y elegí a qué cliente corresponde.">
        {{ $this->form }}

        <div class="mt-4 flex gap-2">
            <x-filament::button wire:click="previsualizar" icon="heroicon-o-eye">
                Ver qué pidió
            </x-filament::button>
        </div>
    </x-filament::section>

    @if($mostrarPrevia && count($vistaPrevia) > 0)
        @php
            $total = collect($vistaPrevia)->sum('subtotal');
        @endphp

        <x-filament::section heading="Revisá antes de crear el pedido"
            description="Los precios son los de hoy, no los que tenía la lista cuando el cliente la completó.">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Producto</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Presentación</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500">Cantidad</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500">Precio</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($vistaPrevia as $item)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $item['nombre'] }}</td>
                                <td class="px-3 py-2 text-gray-400">{{ $item['unidad'] }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ $item['cantidad'] }}</td>
                                <td class="px-3 py-2 text-right text-gray-400">${{ number_format($item['precio'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-semibold">${{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 dark:border-gray-700">
                            <td colspan="4" class="px-3 py-3 text-right font-semibold">Total</td>
                            <td class="px-3 py-3 text-right text-lg font-bold">${{ number_format($total, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-5">
                <x-filament::button
                    wire:click="confirmar"
                    wire:confirm="¿Crear el pedido con estos {{ count($vistaPrevia) }} productos?"
                    color="success"
                    icon="heroicon-o-check">
                    Crear el pedido
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
