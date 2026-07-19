<x-filament-panels::page>
    <x-filament::section heading="Cliente y entrega">
        {{ $this->form }}
    </x-filament::section>

    <x-filament::section heading="Buscar producto" description="Escribí un nombre o marca. Hacé clic en un resultado para agregarlo abajo.">
        <div class="relative">
            <input
                type="text"
                wire:model.live.debounce.400ms="busqueda"
                placeholder="Ej: aceite de coco, chía graal..."
                autocomplete="off"
                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
            />

            @if(count($resultados) > 0)
                <div class="mt-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    @foreach($resultados as $r)
                        <button
                            type="button"
                            wire:click="agregarProducto({{ $r['id'] }})"
                            wire:key="resultado-{{ $r['id'] }}"
                            class="flex w-full items-center justify-between gap-4 border-b border-gray-100 px-3 py-2 text-left text-sm last:border-b-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            <span class="min-w-0">
                                <span class="font-medium text-gray-950 dark:text-white">{{ $r['nombre'] }}</span>
                                <span class="text-gray-400">— {{ $r['unidad'] }}</span>
                                @if($r['marca'])<span class="text-gray-400">· {{ $r['marca'] }}</span>@endif
                                @if($r['stock'] <= 0)<span class="ml-1 text-[10px] font-semibold uppercase text-red-500">Sin stock</span>@endif
                            </span>
                            <span class="shrink-0 font-semibold text-gray-950 dark:text-white">${{ number_format($r['precio'], 0, ',', '.') }}</span>
                        </button>
                    @endforeach
                </div>
            @elseif(mb_strlen(trim($busqueda)) >= 2)
                <p class="mt-2 text-sm text-gray-400">Sin resultados para "{{ $busqueda }}".</p>
            @endif
        </div>
    </x-filament::section>

    @if(count($items) > 0)
        <x-filament::section heading="Productos del pedido">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Producto</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500">Cantidad</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500">Precio</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500">Subtotal</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($items as $presentacionId => $item)
                            <tr wire:key="item-{{ $presentacionId }}">
                                <td class="px-3 py-2">
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $item['nombre'] }}</span>
                                    <span class="text-gray-400">— {{ $item['unidad'] }}</span>
                                    @if($item['marca'])<span class="block text-xs text-gray-400">{{ $item['marca'] }}</span>@endif
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" wire:click="cambiarCantidad({{ $presentacionId }}, -1)"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/20">−</button>
                                        <input type="number" min="1" wire:model.live.debounce.500ms="items.{{ $presentacionId }}.cantidad"
                                            class="fi-input w-14 rounded-lg border-none bg-white px-1 py-1 text-center text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20" />
                                        <button type="button" wire:click="cambiarCantidad({{ $presentacionId }}, 1)"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/20">+</button>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-right text-gray-500">${{ number_format($item['precio'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">${{ number_format($item['precio'] * $item['cantidad'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" wire:click="quitarProducto({{ $presentacionId }})" class="text-gray-400 hover:text-red-500">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-700">
                <span class="text-sm text-gray-500">Total</span>
                <span class="text-lg font-bold text-gray-950 dark:text-white">${{ number_format($this->total, 0, ',', '.') }}</span>
            </div>
        </x-filament::section>

        <x-filament::button wire:click="crearPedido" icon="heroicon-o-check" size="lg">
            Crear pedido
        </x-filament::button>
    @endif
</x-filament-panels::page>
