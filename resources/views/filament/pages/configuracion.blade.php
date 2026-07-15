<x-filament-panels::page>
    <x-filament::section heading="Envío" description="Definí a partir de qué monto de compra el envío sale gratis.">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button wire:click="guardar" icon="heroicon-o-check">
                Guardar
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
