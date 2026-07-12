@php
    $record = $getRecord();
    $record->load('pagos');
    $pagado = $record->pagos->sum('monto');
    $saldo = $record->total - $pagado;
@endphp

<div>
    <div class="flex gap-6 mb-4 text-sm">
        <div>
            <span class="text-gray-400">Pagado:</span>
            <span class="font-bold text-green-600">${{ number_format($pagado, 0, ',', '.') }}</span>
        </div>
        <div>
            <span class="text-gray-400">Saldo:</span>
            <span class="font-bold {{ $saldo <= 0 ? 'text-green-600' : 'text-red-500' }}">
                ${{ number_format(abs($saldo), 0, ',', '.') }}
                @if($saldo <= 0) ✓ @endif
            </span>
        </div>
    </div>

    @if($record->pagos->count())
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Fecha</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Método</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-500">Monto</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Notas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($record->pagos as $pago)
                    <tr>
                        <td class="px-4 py-2">{{ $pago->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $pago->metodo === 'efectivo' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' :
                                   ($pago->metodo === 'transferencia' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' :
                                   'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300') }}">
                                {{ \App\Models\Pago::METODOS[$pago->metodo] ?? $pago->metodo }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right font-semibold">${{ number_format($pago->monto, 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-gray-400">{{ $pago->notas ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="text-sm text-gray-400">Sin pagos registrados.</p>
    @endif
</div>
