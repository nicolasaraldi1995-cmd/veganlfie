<?php

namespace App\Concerns;

trait HasMediaUrl
{
    /**
     * Sirve todo a través de /media/{path} (ver MediaController) en vez de
     * la URL pública del disco directamente -- el dominio del bucket S3
     * devuelve 503 en el embed cruzado de <img>, aunque el archivo esté
     * perfecto (confirmado con curl y navegación directa).
     */
    protected function resolveMediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return url('/media/'.$path);
    }
}
