<?php

namespace App\Filament\Resources\ComboResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\ComboResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCombo extends EditRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = ComboResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ComboResource::limpiarPrecioSegunTipo($data, $this->data['tipo_precio'] ?? null);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
