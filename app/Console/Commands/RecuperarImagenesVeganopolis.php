<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('productos:recuperar-imagenes-veganopolis {--umbral=82} {--limite=} {--tanda=10} {--dry-run}')]
#[Description('Busca en veganopolis.com.ar (la web anterior) la foto de cada producto activo sin imagen real y la sube al bucket S3')]
class RecuperarImagenesVeganopolis extends Command
{
    private const BASE_URL = 'https://veganopolis.com.ar';

    public function handle(): int
    {
        $umbral = (float) $this->option('umbral');
        $limite = $this->option('limite');
        $tanda = max(1, (int) $this->option('tanda'));
        $dryRun = (bool) $this->option('dry-run');

        // El campo "imagen" puede tener una ruta cargada y aun así no haber
        // foto real: el disco local de producción se borra en cada deploy, así
        // que la mayoría de esas rutas ya apuntan a un archivo que no existe
        // más. Se chequea contra 's3' (no 'public') porque ese es el disco al
        // que este comando sube todo, y el destino final una vez activado
        // FILAMENT_FILESYSTEM_DISK=s3 -- así una segunda corrida no vuelve a
        // reprocesar lo que ya se recuperó en una tanda anterior.
        $productos = Producto::activos()
            ->orderBy('id')
            ->get(['id', 'nombre', 'imagen'])
            ->filter(fn (Producto $p) => blank($p->imagen) || ! Storage::disk('s3')->exists($p->imagen))
            ->values();

        if ($limite !== null) {
            $productos = $productos->take((int) $limite);
        }

        $this->info('Productos sin foto real: '.$productos->count().($dryRun ? ' (dry-run: no se sube ni guarda nada)' : ''));

        $bar = $this->output->createProgressBar($productos->count());
        $aplicados = 0;
        $revisar = [];

        // La web vieja es lenta (hosting legado): buscar de a uno tardaba ~6s
        // por producto (horas para todo el catálogo). Se busca en tandas
        // concurrentes -- moderado a propósito para no tumbar un hosting
        // viejo -- y solo la descarga+subida de cada match queda secuencial.
        foreach ($productos->chunk($tanda) as $grupo) {
            $candidatosPorId = $this->mejoresCandidatos($grupo);

            foreach ($grupo as $producto) {
                $candidato = $candidatosPorId[$producto->id] ?? null;

                if ($candidato && $candidato['score'] >= $umbral) {
                    if ($dryRun || $this->aplicar($producto, $candidato)) {
                        $aplicados++;
                    } else {
                        $revisar[] = [$producto, $candidato];
                    }
                } else {
                    $revisar[] = [$producto, $candidato];
                }

                $bar->advance();
            }

            usleep(200_000);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? 'Coincidencias encontradas: ' : 'Imágenes aplicadas: ').$aplicados);
        $this->warn('A revisar a mano ('.count($revisar).'):');

        foreach ($revisar as [$producto, $candidato]) {
            $this->line(sprintf(
                '  - %s => %s (score %s)',
                $producto->nombre,
                $candidato['nombre'] ?? 'sin coincidencia',
                $candidato ? round($candidato['score']) : '-'
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{id: string, archivo: string, nombre: string, score: float}  $candidato
     */
    private function aplicar(Producto $producto, array $candidato): bool
    {
        $respuesta = $this->cliente()->get(self::BASE_URL.'/imagenes/'.$candidato['archivo']);

        if (! $respuesta->successful() || ! str_starts_with((string) $respuesta->header('Content-Type'), 'image/')) {
            return false;
        }

        $ruta = 'productos/'.Str::slug($producto->nombre).'-'.$producto->id.'.jpg';
        Storage::disk('s3')->put($ruta, $respuesta->body());
        $producto->update(['imagen' => $ruta]);

        return true;
    }

    /**
     * Busca los productos de un grupo en paralelo (una consulta por producto)
     * y, para los que no trajeron ningún candidato, reintenta en paralelo con
     * un nombre más corto. Devuelve el mejor candidato puntuado por id.
     *
     * @param  Collection<int, Producto>  $productos
     * @return array<int, array{id: string, archivo: string, nombre: string, score: float}|null>
     */
    private function mejoresCandidatos(Collection $productos): array
    {
        $candidatosPorId = $this->buscarEnParalelo($productos->pluck('nombre', 'id'));

        $sinResultado = $productos->filter(fn (Producto $p) => $candidatosPorId[$p->id]->isEmpty());

        if ($sinResultado->isNotEmpty()) {
            // El nombre completo suele traer calificadores (tamaño, sabor,
            // marca) que no están en la web vieja: reintentamos con solo las
            // primeras palabras para tener al menos algún candidato.
            $consultasCortas = $sinResultado->mapWithKeys(fn (Producto $p) => [$p->id => $this->primerasPalabras($p->nombre)]);

            foreach ($this->buscarEnParalelo($consultasCortas) as $id => $candidatos) {
                $candidatosPorId[$id] = $candidatos;
            }
        }

        return $productos->mapWithKeys(function (Producto $p) use ($candidatosPorId) {
            $mejor = $candidatosPorId[$p->id]
                ->map(fn (array $c) => [...$c, 'score' => $this->similitud($p->nombre, $c['nombre'])])
                ->sortByDesc('score')
                ->first();

            return [$p->id => $mejor];
        })->all();
    }

    private function primerasPalabras(string $nombre, int $cantidad = 2): string
    {
        return implode(' ', array_slice(explode(' ', $nombre), 0, $cantidad));
    }

    /**
     * @param  Collection<int, string>  $consultasPorId
     * @return Collection<int, Collection<int, array{id: string, archivo: string, nombre: string}>>
     */
    private function buscarEnParalelo(Collection $consultasPorId): Collection
    {
        $respuestas = Http::pool(fn (Pool $pool) => $consultasPorId->map(
            fn (string $query, int $id) => $pool->as((string) $id)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->withOptions(['verify' => resource_path('certs/veganopolis-chain.pem')])
                ->get(self::BASE_URL.'/index.php', ['buscar' => $query])
        )->all());

        return $consultasPorId->keys()->mapWithKeys(function (int $id) use ($respuestas) {
            $respuesta = $respuestas[(string) $id];
            $html = $respuesta instanceof Response ? (string) $respuesta->body() : '';

            return [$id => $this->parsearCandidatos($html)];
        });
    }

    /**
     * @return Collection<int, array{id: string, archivo: string, nombre: string}>
     */
    private function parsearCandidatos(string $html): Collection
    {
        preg_match_all(
            '/<a href="producto\.php\?id=(\d+)">\s*<img class="card-img-top" src="([^"]+?)\s*">\s*<\/a>\s*<div class="card-body">\s*<a href="producto\.php\?id=\d+" style="text-decoration: none;">\s*<h6 class="card-title[^"]*"[^>]*>([^<]*)<\/h6>/s',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        return collect($matches)->map(fn (array $m) => [
            'id' => $m[1],
            'archivo' => basename(trim($m[2])),
            'nombre' => trim(html_entity_decode($m[3])),
        ]);
    }

    /**
     * La web vieja usa una raíz de Let's Encrypt (ISRG Root YR) tan nueva que
     * todavía no está en el bundle de CAs del servidor de producción, y
     * además no manda la cadena intermedia -- el navegador la resuelve solo,
     * cURL no. Se arma un bundle propio con esa raíz + la intermedia
     * faltante + las raíces estándar, en vez de desactivar la verificación.
     */
    private function cliente(): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->withOptions(['verify' => resource_path('certs/veganopolis-chain.pem')]);
    }

    private function similitud(string $a, string $b): float
    {
        $na = $this->normalizar($a);
        $nb = $this->normalizar($b);

        if ($na === $nb) {
            return 100.0;
        }

        // Un nombre suele ser el otro más un calificador (sabor, tamaño, "x2u"):
        // si uno está contenido entero en el otro, es una coincidencia fuerte
        // aunque el porcentaje de caracteres en común no lo refleje tan bien.
        $corta = strlen($na) <= strlen($nb) ? $na : $nb;
        $larga = strlen($na) <= strlen($nb) ? $nb : $na;

        if ($corta !== '' && str_contains($larga, $corta)) {
            return 95.0;
        }

        similar_text($na, $nb, $porcentaje);

        return $porcentaje;
    }

    private function normalizar(string $s): string
    {
        $s = mb_strtolower($s);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9 ]/', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }
}
