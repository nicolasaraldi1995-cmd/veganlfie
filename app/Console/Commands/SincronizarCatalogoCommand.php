<?php

namespace App\Console\Commands;

use App\Services\SincronizarCatalogo;
use Illuminate\Console\Command;

/**
 * Pone el catálogo a tono con la lista de precios definitiva: mueve de marca,
 * renombra y da de baja lo que ya no está.
 *
 * Por defecto no toca nada: muestra qué haría. Recién con --aplicar escribe.
 */
class SincronizarCatalogoCommand extends Command
{
    protected $signature = 'catalogo:sincronizar
        {archivo : Ruta de la lista de precios}
        {--fila=5 : Fila donde están los encabezados}
        {--aplicar : Escribir los cambios (sin esto sólo los muestra)}';

    protected $description = 'Compara el catálogo con la lista de precios y lo pone a tono';

    public function handle(SincronizarCatalogo $servicio): int
    {
        $archivo = $this->argument('archivo');

        if (! is_file($archivo)) {
            $this->error("No encuentro el archivo: {$archivo}");

            return self::FAILURE;
        }

        $this->info('Leyendo la lista...');
        $plan = $servicio->analizar($archivo, (int) $this->option('fila'));

        $this->newLine();
        $this->table(['Qué', 'Cuántos'], [
            ['Se quedan como están', $plan['sinCambios']],
            ['Cambian de marca', count($plan['cambiosDeMarca'])],
            ['Cambian de nombre', count($plan['cambiosDeNombre'])],
            ['Se dan de baja', count($plan['bajas'])],
            ['  ...de esas, parecidas a otra', count($plan['dudosos'])],
        ]);

        $this->mostrar('CAMBIOS DE MARCA', $plan['cambiosDeMarca'],
            fn ($c) => sprintf('%s   %s  ->  %s', mb_substr($c['nombre'], 0, 42), $c['marcaVieja'], $c['marcaNueva']));

        $this->mostrar('CAMBIOS DE NOMBRE', $plan['cambiosDeNombre'],
            fn ($c) => sprintf('[%d%%] %s  ->  %s   (%s)', $c['parecido'], mb_substr($c['nombreViejo'], 0, 40), mb_substr($c['nombreNuevo'], 0, 40), $c['marca']));

        $this->mostrar('BAJAS', $plan['bajas'],
            fn ($c) => sprintf('%s   (%s)', mb_substr($c['nombre'], 0, 46), $c['marca']));

        // Se dan de baja igual, pero se listan aparte: parecían renombres y no
        // lo son, y conviene que quede dicho por qué.
        $this->mostrar('SE DAN DE BAJA, NO SE RENOMBRAN', $plan['dudosos'],
            fn ($c) => sprintf('%s  ~  %s   (%s) -- %s', mb_substr($c['nombreViejo'], 0, 38), mb_substr($c['nombreNuevo'], 0, 38), $c['marca'], $c['motivo']));

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->warn('No se cambió nada. Volvé a correrlo con --aplicar para que se guarde.');

            return self::SUCCESS;
        }

        $hechos = $servicio->aplicar($plan);

        $this->newLine();
        $this->info(sprintf(
            'Listo: %d movidos de marca, %d renombrados, %d dados de baja, %d duplicados unificados.',
            $hechos['marcas'], $hechos['nombres'], $hechos['bajas'], $hechos['duplicados']
        ));
        $this->line('Las bajas son lógicas: el producto queda en la base con su foto, sólo deja de mostrarse.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function mostrar(string $titulo, array $items, callable $formato): void
    {
        if ($items === []) {
            return;
        }

        $this->newLine();
        $this->line("<comment>{$titulo}</comment> (".count($items).')');

        foreach ($items as $item) {
            $this->line('  '.$formato($item));
        }
    }
}
