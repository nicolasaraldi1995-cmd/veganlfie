<x-filament-panels::page>
    <x-filament::section
        heading="Pedido que armó el cliente"
        description="Subí el archivo que te mandó por WhatsApp desde la lista de precios y elegí a qué cliente corresponde.">
        {{ $this->form }}

        {{--
            El archivo se lee acá en el navegador y solo se manda el texto: no se
            sube nada al servidor. Es un .json de unos cientos de bytes y así se
            evita el camino de subida de archivos de Livewire, que en este
            proyecto viene dando problemas.
        --}}
        <div class="mt-6" x-data="{ nombre: '', error: '' }">
            <label class="block text-sm font-medium mb-2">Archivo del pedido</label>

            <label class="flex items-center gap-3 px-4 py-3 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 cursor-pointer hover:border-primary-500 transition">
                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l-3 3m3-3l3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/>
                </svg>
                <span class="text-sm" x-text="nombre || 'Elegir el .json que te mandó el cliente'"></span>
                <input type="file" accept=".json,application/json,text/plain" class="hidden"
                    @change="
                        error = '';
                        nombre = '';
                        const archivo = $event.target.files[0];
                        if (! archivo) return;
                        const lector = new FileReader();
                        lector.onload = () => { nombre = archivo.name; $wire.set('contenido', lector.result); };
                        lector.onerror = () => { error = 'No se pudo leer el archivo.'; };
                        lector.readAsText(archivo);
                    ">
            </label>

            <p x-show="error" x-text="error" class="mt-2 text-sm text-danger-600"></p>
            <p class="mt-2 text-xs text-gray-500">Es el archivo que genera la lista de precios cuando el cliente toca "Enviar pedido".</p>
        </div>

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
