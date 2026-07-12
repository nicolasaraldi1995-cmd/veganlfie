@php $d = $this->getData(); @endphp

<x-filament-widgets::widget>
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">

        <a href="{{ route('filament.admin.resources.pedidos.index') }}" class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-6 hover:border-primary-500/50 hover:shadow-md transition-all group">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-xs text-gray-400 uppercase tracking-wider group-hover:text-primary-400 transition">Ventas hoy</p>
            </div>
            <p class="text-3xl font-bold text-green-500">${{ number_format($d['ventasHoy'], 0, ',', '.') }}</p>
            <p class="text-xs text-gray-400 mt-2">{{ $d['pedidosHoy'] }} {{ $d['pedidosHoy'] === 1 ? 'pedido' : 'pedidos' }}</p>
        </a>

        <a href="{{ route('filament.admin.resources.pedidos.index') }}" class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-6 hover:border-primary-500/50 hover:shadow-md transition-all group">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
                </div>
                <p class="text-xs text-gray-400 uppercase tracking-wider group-hover:text-primary-400 transition">Ventas del mes</p>
            </div>
            <p class="text-3xl font-bold text-green-500">${{ number_format($d['ventasMes'], 0, ',', '.') }}</p>
            <p class="text-xs text-gray-400 mt-2">{{ $d['pedidosMes'] }} pedidos</p>
        </a>

        <a href="{{ route('filament.admin.resources.gastos.index') }}" class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-6 hover:border-primary-500/50 hover:shadow-md transition-all group">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                </div>
                <p class="text-xs text-gray-400 uppercase tracking-wider group-hover:text-primary-400 transition">Gastos del mes</p>
            </div>
            <p class="text-3xl font-bold text-red-500">${{ number_format($d['gastosMes'], 0, ',', '.') }}</p>
            <p class="text-xs text-gray-400 mt-2">Ver detalle →</p>
        </a>

        <div class="rounded-2xl border-2 p-6 {{ $d['balance'] >= 0 ? 'border-green-500/30 bg-green-500/5' : 'border-red-500/30 bg-red-500/5' }}">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl {{ $d['balance'] >= 0 ? 'bg-green-500/10' : 'bg-red-500/10' }} flex items-center justify-center">
                    @if($d['balance'] >= 0)
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
                    @else
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306 6.43l.776 2.898m0 0l3.182-5.511m-3.182 5.51l-5.511-3.181"/></svg>
                    @endif
                </div>
                <p class="text-xs text-gray-400 uppercase tracking-wider">{{ $d['balance'] >= 0 ? 'Ganancia' : 'Pérdida' }}</p>
            </div>
            <p class="text-3xl font-bold {{ $d['balance'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                {{ $d['balance'] >= 0 ? '' : '-' }}${{ number_format(abs($d['balance']), 0, ',', '.') }}
            </p>
            <p class="text-xs text-gray-400 mt-2">Ingresos − Gastos</p>
        </div>

    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mt-4">

        <a href="{{ route('filament.admin.resources.pedidos.index') }}" class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-6 hover:border-amber-500/50 hover:shadow-md transition-all group">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-xs text-gray-400 uppercase tracking-wider group-hover:text-amber-400 transition">Pendientes</p>
            </div>
            <p class="text-3xl font-bold {{ $d['pendientes'] > 0 ? 'text-amber-500' : '' }}">{{ $d['pendientes'] }}</p>
        </a>

        <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-primary-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                </div>
                <p class="text-xs text-gray-400 uppercase tracking-wider">Productos</p>
            </div>
            <p class="text-3xl font-bold">{{ number_format($d['productosActivos'], 0, ',', '.') }}</p>
        </div>

        <a href="{{ route('filament.admin.resources.presentacions.index') }}" class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-6 hover:border-red-500/50 hover:shadow-md transition-all group">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl {{ $d['sinStock'] > 0 ? 'bg-red-500/10' : 'bg-green-500/10' }} flex items-center justify-center">
                    <svg class="w-5 h-5 {{ $d['sinStock'] > 0 ? 'text-red-500' : 'text-green-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </div>
                <p class="text-xs text-gray-400 uppercase tracking-wider group-hover:text-red-400 transition">Sin stock</p>
            </div>
            <p class="text-3xl font-bold {{ $d['sinStock'] > 0 ? 'text-red-500' : 'text-green-500' }}">{{ number_format($d['sinStock'], 0, ',', '.') }}</p>
        </a>

        <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-primary-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                </div>
                <p class="text-xs text-gray-400 uppercase tracking-wider">Pedidos del mes</p>
            </div>
            <p class="text-3xl font-bold">{{ $d['pedidosMes'] }}</p>
        </div>

    </div>
</x-filament-widgets::widget>
