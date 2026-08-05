<?php

namespace App\Filament\Resources\MarcaResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\MarcaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarca extends CreateRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = MarcaResource::class;
}
