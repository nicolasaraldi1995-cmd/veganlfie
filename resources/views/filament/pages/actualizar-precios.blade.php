<x-filament-panels::page>
    <x-filament::section heading="Actualizar precios" description="Seleccioná una marca y aplicá un porcentaje de aumento o baja a todos sus productos.">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button wire:click="generarPreview" icon="heroicon-o-eye">
                Vista previa
            </x-filament::button>
        </div>
    </x-filament::section>

    @if($showPreview && count($preview) > 0)
        <x-filament::section heading="Vista previa" description="Así quedarían los precios con {{ $porcentaje >= 0 ? '+' : '' }}{{ $porcentaje }}%.">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Producto</th>
                            <th class="px-3 py-2 text-left font-medium">Unidad</th>
                            <th class="px-3 py-2 text-right font-medium">Precio actual</th>
                            <th class="px-3 py-2 text-right font-medium">Precio nuevo</th>
                            <th class="px-3 py-2 text-right font-medium">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($preview as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['producto'] }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $row['unidad'] }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format($row['precio_actual'], 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-medium {{ $porcentaje >= 0 ? 'text-green-600' : 'text-red-600' }}">${{ number_format($row['precio_nuevo'], 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right text-gray-400">{{ $porcentaje >= 0 ? '+' : '' }}${{ number_format($row['precio_nuevo'] - $row['precio_actual'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <x-filament::button wire:click="aplicarAumento" color="success" icon="heroicon-o-check">
                    Aplicar a todos ({{ count($preview) }} presentaciones)
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
