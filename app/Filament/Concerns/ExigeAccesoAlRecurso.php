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

        // Y además lo que chequea Filament de fábrica por registro (canEdit,
        // canView, canCreate). Sin el parent, sobreescribir authorizeAccess
        // apagaba esos chequeos en silencio: hoy no rompe nada, pero el día que
        // alguien agregue un canEdit por registro, quedaría sin efecto.
        parent::authorizeAccess();
    }
}
