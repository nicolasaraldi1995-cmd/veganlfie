<?php

namespace App\Filament\Resources\CategoriaResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\CategoriaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoria extends CreateRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = CategoriaResource::class;
}
