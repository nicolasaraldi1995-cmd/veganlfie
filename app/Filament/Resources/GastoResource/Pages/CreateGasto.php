<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\GastoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGasto extends CreateRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = GastoResource::class;
}
