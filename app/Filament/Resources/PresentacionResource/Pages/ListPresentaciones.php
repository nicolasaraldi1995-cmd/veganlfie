<?php

namespace App\Filament\Resources\PresentacionResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\PresentacionResource;
use Filament\Resources\Pages\ListRecords;

class ListPresentaciones extends ListRecords
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = PresentacionResource::class;
}
