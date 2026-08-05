<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = UserResource::class;
}
