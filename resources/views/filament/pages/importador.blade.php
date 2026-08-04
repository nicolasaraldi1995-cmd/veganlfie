<x-filament-panels::page>

    {{-- STEP 1: Upload --}}
    @if($step === 'upload')
        <div class="space-y-4">
            <x-filament::section heading="Paso 1: Subir archivo" description="Seleccioná un archivo Excel (.xlsx) o CSV con tus productos.">
                {{ $this->form }}

                <div class="mt-4">
                    <x-filament::button wire:click="loadHeaders" icon="heroicon-o-arrow-right">
                        Siguiente: Mapear columnas
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    @endif

    {{-- STEP 2: Map columns --}}
    @if($step === 'map')
        <div class="space-y-4">
            <x-filament::section heading="Paso 2: Mapear columnas" description="Indicá qué columna del archivo corresponde a cada campo. Nombre y Marca son obligatorios.">

                <div class="text-sm text-gray-500 mb-4">
                    Columnas detectadas: <strong>{{ implode(', ', $headers) }}</strong>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach(['nombre' => 'Nombre *', 'marca' => 'Marca *', 'categoria' => 'Categoría', 'unidad' => 'Unidad', 'precio' => 'Precio', 'stock' => 'Stock', 'sin_tacc' => 'Sin TACC', 'congelado' => 'Congelado', 'nuevo' => 'Nuevo'] as $field => $label)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
                            <select wire:model="columnMap.{{ $field }}" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">— No mapear —</option>
                                @foreach($headers as $header)
                                    <option value="{{ $header }}">{{ $header }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="actualizar_existentes" class="rounded border-gray-300 text-primary-600">
                        Actualizar productos existentes (precio y datos)
                    </label>
                </div>

                <div class="mt-4 flex gap-2">
                    <x-filament::button wire:click="generatePreview" icon="heroicon-o-eye">
                        Ver preview
                    </x-filament::button>
                    <x-filament::button wire:click="reset_form" color="gray" icon="heroicon-o-arrow-left">
                        Volver
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    @endif

    {{-- STEP 3: Preview --}}
    @if($step === 'preview')
        <div class="space-y-4">
            <x-filament::section heading="Paso 3: Vista previa" description="Revisá que los datos se vean correctos antes de importar.">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-blue-50 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-blue-700">{{ $previewData['total_filas'] ?? 0 }}</p>
                        <p class="text-sm text-blue-600">Filas totales</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-green-700">{{ count($previewData['marcas_nuevas'] ?? []) }}</p>
                        <p class="text-sm text-green-600">Marcas nuevas</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-purple-700">{{ count($previewData['categorias_nuevas'] ?? []) }}</p>
                        <p class="text-sm text-purple-600">Categorías nuevas</p>
                    </div>
                </div>

                @if(!empty($previewData['marcas_nuevas']))
                    <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                        <p class="text-sm font-medium text-green-800 mb-1">Se crearán estas marcas:</p>
                        <p class="text-sm text-green-600">{{ implode(', ', $previewData['marcas_nuevas']) }}</p>
                    </div>
                @endif

                @if(!empty($previewData['categorias_nuevas']))
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 mb-4">
                        <p class="text-sm font-medium text-purple-800 mb-1">Se crearán estas categorías:</p>
                        <p class="text-sm text-purple-600">{{ implode(', ', $previewData['categorias_nuevas']) }}</p>
                    </div>
                @endif

                {{-- Lo que el archivo deja afuera. El importador sólo agrega y
                     actualiza, así que sin esto un producto que el proveedor
                     sacó de la lista queda publicado para siempre. --}}
                @if(($resumenSync['bajas'] ?? 0) > 0 || ($resumenSync['cambiosDeMarca'] ?? 0) > 0)
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                        <p class="text-sm font-semibold text-amber-900 mb-3">Comparado con lo que hay publicado hoy</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                            <div class="bg-white rounded-lg p-3 border border-amber-200">
                                <p class="text-xl font-bold text-amber-800">{{ $resumenSync['cambiosDeMarca'] ?? 0 }}</p>
                                <p class="text-xs text-amber-700 mb-1">productos cambiaron de marca</p>
                                @foreach($resumenSync['ejemplosMarca'] ?? [] as $c)
                                    <p class="text-[11px] text-gray-500 truncate">{{ $c['nombre'] }}: {{ $c['marcaVieja'] }} → {{ $c['marcaNueva'] }}</p>
                                @endforeach
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-amber-200">
                                <p class="text-xl font-bold text-amber-800">{{ $resumenSync['bajas'] ?? 0 }}</p>
                                <p class="text-xs text-amber-700 mb-1">no están en este archivo</p>
                                @foreach($resumenSync['ejemplosBaja'] ?? [] as $c)
                                    <p class="text-[11px] text-gray-500 truncate">{{ $c['nombre'] }} ({{ $c['marca'] }})</p>
                                @endforeach
                            </div>
                        </div>

                        <label class="flex items-start gap-2 text-sm text-amber-900 cursor-pointer">
                            <input type="checkbox" wire:model="sincronizar" class="mt-0.5 rounded border-amber-400 text-amber-600">
                            <span>
                                <b>Poner el catálogo a tono con este archivo.</b>
                                Mueve los productos a la marca que les corresponde y da de baja los que ya no están.
                                <span class="block text-xs text-amber-700 mt-0.5">
                                    La baja no borra nada: el producto queda con su foto y su historial, sólo deja de mostrarse en la web.
                                </span>
                            </span>
                        </label>
                    </div>
                @endif

                {{-- Preview table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Nombre</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Marca</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Categoría</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Unidad</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600">Precio</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach(($previewData['preview'] ?? []) as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['nombre'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['marca'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['categoria'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['unidad'] ?? '-' }}</td>
                                    <td class="px-3 py-2 text-right">${{ number_format((float)($row['precio'] ?? 0), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-400 mt-2">Mostrando primeras 20 filas de {{ $previewData['total_filas'] ?? 0 }}</p>

                <div class="mt-4 flex gap-2">
                    <x-filament::button wire:click="runImport" icon="heroicon-o-arrow-down-tray" color="success">
                        Importar todo
                    </x-filament::button>
                    <x-filament::button wire:click="$set('step', 'map')" color="gray" icon="heroicon-o-arrow-left">
                        Volver al mapeo
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    @endif

    {{-- STEP 4: Result --}}
    @if($step === 'result')
        <div class="space-y-4">
            <x-filament::section heading="Importación completada">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <p class="text-3xl font-bold text-green-700">{{ $importResult['productos_creados'] ?? 0 }}</p>
                        <p class="text-sm text-green-600">Productos creados</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                        <p class="text-3xl font-bold text-blue-700">{{ $importResult['productos_actualizados'] ?? 0 }}</p>
                        <p class="text-sm text-blue-600">Productos actualizados</p>
                    </div>
                    <div class="bg-indigo-50 rounded-lg p-4 text-center">
                        <p class="text-3xl font-bold text-indigo-700">{{ $importResult['presentaciones_creadas'] ?? 0 }}</p>
                        <p class="text-sm text-indigo-600">Presentaciones creadas</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4 text-center">
                        <p class="text-3xl font-bold text-purple-700">{{ $importResult['presentaciones_actualizadas'] ?? 0 }}</p>
                        <p class="text-sm text-purple-600">Presentaciones actualizadas</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-teal-50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-teal-700">{{ $importResult['marcas_creadas'] ?? 0 }}</p>
                        <p class="text-sm text-teal-600">Marcas nuevas</p>
                    </div>
                    <div class="bg-pink-50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-pink-700">{{ $importResult['categorias_creadas'] ?? 0 }}</p>
                        <p class="text-sm text-pink-600">Categorías nuevas</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-gray-700">{{ $importResult['filas_saltadas'] ?? 0 }}</p>
                        <p class="text-sm text-gray-600">Filas saltadas</p>
                    </div>
                    @if(!empty($syncResult))
                        <div class="bg-amber-50 rounded-lg p-3 text-center">
                            <p class="text-xl font-bold text-amber-800">{{ $syncResult['marcas'] ?? 0 }}</p>
                            <p class="text-sm text-amber-700">Movidos de marca</p>
                        </div>
                        <div class="bg-amber-50 rounded-lg p-3 text-center">
                            <p class="text-xl font-bold text-amber-800">{{ $syncResult['bajas'] ?? 0 }}</p>
                            <p class="text-sm text-amber-700">Dados de baja</p>
                        </div>
                        <div class="bg-amber-50 rounded-lg p-3 text-center">
                            <p class="text-xl font-bold text-amber-800">{{ $syncResult['duplicados'] ?? 0 }}</p>
                            <p class="text-sm text-amber-700">Duplicados unificados</p>
                        </div>
                    @endif
                </div>

                @if(!empty($importResult['errores']))
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <p class="text-sm font-medium text-red-800 mb-2">Errores:</p>
                        @foreach($importResult['errores'] as $error)
                            <p class="text-sm text-red-600">• {{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4">
                    <x-filament::button wire:click="reset_form" icon="heroicon-o-arrow-path">
                        Nueva importación
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    @endif

</x-filament-panels::page>
