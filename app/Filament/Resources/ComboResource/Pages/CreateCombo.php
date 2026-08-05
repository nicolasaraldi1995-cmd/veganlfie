<?php

namespace App\Filament\Resources\ComboResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\ComboResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCombo extends CreateRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = ComboResource::class;
}
