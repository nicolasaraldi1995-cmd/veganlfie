<?php

namespace App\Filament\Concerns;

/**
 * Filament deja vacío el chequeo de acceso de las pantallas de recurso
 * (ListRecords::authorizeAccess), así que quien es del panel llega a todas las
 * de todos los recursos, y quien no lo es puede montar el componente por
 * dentro salteando la dirección. El middleware tapa la URL, no el componente.
 *
 * Esto vuelve a preguntar, en cada pantalla, lo mismo que dice el recurso.
 */
trait ExigeAccesoAlRecurso
{
    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canAccess(), 403);
    }
}
