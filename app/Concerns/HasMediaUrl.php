<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasMediaUrl
{
    protected function resolveMediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(config('filament.default_filesystem_disk'))->url($path);
    }
}
