<?php

namespace App\Filament\Resources\ProductoResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\ProductoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProducto extends CreateRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = ProductoResource::class;
}
