<?php

namespace App\Filament\Resources\BannerResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\BannerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBanner extends CreateRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = BannerResource::class;
}
