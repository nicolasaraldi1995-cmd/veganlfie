<x-filament-panels::page>
    <x-filament::section heading="Clientes que deben" description="Todos los clientes con saldo pendiente, del más antiguo al más reciente.">
        @if(count($clientesConSaldo) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Celular</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Debe desde</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500">Saldo</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($clientesConSaldo as $cliente)
                            <tr>
                                <td class="px-3 py-2">
                                    <span class="font-medium">{{ $cliente['nombre'] }}</span>
                                    @if($cliente['negocio'])<span class="text-gray-400"> ({{ $cliente['negocio'] }})</span>@endif
                                </td>
                                <td class="px-3 py-2 text-gray-400">{{ $cliente['celular'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-400">{{ $cliente['desde'] }}</td>
                                <td class="px-3 py-2 text-right font-bold text-red-500">${{ number_format($cliente['saldo'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">
                                    <x-filament::button size="xs" wire:click="verClienteConSaldo({{ $cliente['id'] }})" icon="heroicon-o-eye">
                                        Ver
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-center text-gray-400 py-8">Ningún cliente tiene saldo pendiente. 🎉</p>
        @endif
    </x-filament::section>

    <x-filament::section heading="Buscar cliente" description="O elegí un cliente puntual para ver su historial completo, tenga saldo o no.">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button wire:click="verResumen" icon="heroicon-o-eye">
                Ver resumen
            </x-filament::button>
        </div>
    </x-filament::section>

    @if($showResumen && !empty($resumen))
        {{-- Client header --}}
        <x-filament::section>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold">{{ $resumen['cliente']['nombre'] }}</h2>
                    @if($resumen['cliente']['negocio'])<p class="text-sm text-gray-500">{{ $resumen['cliente']['negocio'] }}</p>@endif
                    <p class="text-xs text-gray-400">{{ $resumen['cliente']['email'] }} · {{ $resumen['cliente']['celular'] }}</p>
                </div>
                <div class="flex gap-6 text-right">
                    <div>
                        <p class="text-xs text-gray-400">Total pedidos</p>
                        <p class="text-lg font-bold">${{ number_format($resumen['totalPedidos'], 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Total pagado</p>
                        <p class="text-lg font-bold text-green-600">${{ number_format($resumen['totalPagado'], 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Saldo</p>
                        <p class="text-lg font-bold {{ $resumen['saldoTotal'] <= 0 ? 'text-green-600' : 'text-red-500' }}">
                            ${{ number_format(abs($resumen['saldoTotal']), 0, ',', '.') }}
                            @if($resumen['saldoTotal'] <= 0) ✓ @endif
                        </p>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Orders --}}
        @foreach($resumen['pedidos'] as $pedido)
            <x-filament::section :heading="'Pedido #' . $pedido['id'] . ' — ' . $pedido['fecha']" :collapsed="true" collapsible>
                <div class="flex flex-wrap gap-4 mb-4 text-sm">
                    <div><span class="text-gray-400">Estado:</span> <span class="font-medium">{{ $pedido['estado'] }}</span></div>
                    <div><span class="text-gray-400">Productos:</span> {{ $pedido['items_count'] }}</div>
                    <div><span class="text-gray-400">Total:</span> <span class="font-bold">${{ number_format($pedido['total'], 0, ',', '.') }}</span></div>
                    <div><span class="text-gray-400">Pagado:</span> <span class="font-medium text-green-600">${{ number_format($pedido['pagado'], 0, ',', '.') }}</span></div>
                    <div>
                        <span class="text-gray-400">Saldo:</span>
                        <span class="font-bold {{ $pedido['saldo'] <= 0 ? 'text-green-600' : 'text-red-500' }}">
                            ${{ number_format(abs($pedido['saldo']), 0, ',', '.') }}
                            @if($pedido['saldo'] <= 0) ✓ @endif
                        </span>
                    </div>
                </div>

                @if(count($pedido['pagos']) > 0)
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Pagos registrados</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Fecha</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Método</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-500">Monto</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Notas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($pedido['pagos'] as $pago)
                                    <tr>
                                        <td class="px-3 py-2">{{ $pago['fecha'] }}</td>
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
                    <p class="text-sm text-gray-400">Sin pagos registrados.</p>
                @endif
            </x-filament::section>
        @endforeach

        @if(empty($resumen['pedidos']))
            <x-filament::section>
                <p class="text-center text-gray-400 py-8">Este cliente no tiene pedidos.</p>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
