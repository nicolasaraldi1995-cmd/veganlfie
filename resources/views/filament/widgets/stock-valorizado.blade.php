@php $d = $this->getData(); @endphp

<x-filament-widgets::widget>
    <a href="{{ route('filament.admin.resources.presentacions.index') }}" class="block rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-6 hover:border-primary-500/50 hover:shadow-md transition-all group">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider group-hover:text-primary-400 transition">Mercadería en stock</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ number_format($d['totalUnidades'], 0, ',', '.') }} unidades · {{ $d['porMarca']->count() }} marcas</p>
                </div>
            </div>
            <p class="text-3xl font-bold text-primary-500">${{ number_format($d['totalValor'], 0, ',', '.') }}</p>
        </div>
    </a>
</x-filament-widgets::widget>
