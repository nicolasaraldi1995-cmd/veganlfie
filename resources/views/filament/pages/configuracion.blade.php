<x-filament-panels::page>
    {{ $this->form }}

    <div class="mt-4">
        <x-filament::button wire:click="guardar" icon="heroicon-o-check">
            Guardar
        </x-filament::button>
    </div>
</x-filament-panels::page>
