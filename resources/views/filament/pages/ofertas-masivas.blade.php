<x-filament-panels::page>
    <x-filament::section heading="Configurar oferta" description="Aplicá un descuento porcentual a todas las presentaciones de una marca o categoría.">
        {{ $this->form }}

        <div class="mt-4 flex gap-2">
            <x-filament::button wire:click="generarPreview" icon="heroicon-o-eye">
                Vista previa
            </x-filament::button>
            <x-filament::button
                wire:click="quitarOfertas"
                wire:confirm="¿Quitar la oferta de todas las presentaciones que coincidan con el filtro? Esta acción no se puede deshacer."
                color="danger"
                icon="heroicon-o-x-mark"
            >
                Quitar ofertas
            </x-filament::button>
        </div>
    </x-filament::section>

    @if($showPreview && count($preview) > 0)
        <x-filament::section heading="Vista previa" description="Así quedarían los precios con el {{ $porcentaje }}% de descuento.">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Producto</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Marca</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Unidad</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Precio actual</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Precio oferta</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($preview as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['producto'] }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $row['marca'] }}</td>
                                <td class="px-3 py-2">{{ $row['unidad'] }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format($row['precio_actual'], 2) }}</td>
                                <td class="px-3 py-2 text-right font-medium text-red-600">${{ number_format($row['precio_oferta'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(count($preview) >= 50)
                <p class="text-xs text-gray-400 mt-2">Mostrando primeras 50 presentaciones.</p>
            @endif

            <div class="mt-4">
                <x-filament::button
                    wire:click="aplicarOfertas"
                    wire:confirm="¿Aplicar esta oferta a todas las presentaciones que coincidan con el filtro? La vista previa muestra hasta 50, puede haber más. Esta acción no se puede deshacer."
                    color="success"
                    icon="heroicon-o-check"
                >
                    Aplicar oferta a todas
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
