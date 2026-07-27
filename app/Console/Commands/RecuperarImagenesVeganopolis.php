<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('productos:recuperar-imagenes-veganopolis {--umbral=82} {--dry-run}')]
#[Description('Busca en veganopolis.com.ar (la web anterior) la foto de cada producto activo sin imagen y la sube al bucket S3')]
class RecuperarImagenesVeganopolis extends Command
{
    private const BASE_URL = 'https://veganopolis.com.ar';

    public function handle(): int
    {
        $umbral = (float) $this->option('umbral');
        $dryRun = (bool) $this->option('dry-run');

        $productos = Producto::activos()
            ->where(function ($q) {
                $q->whereNull('imagen')->orWhere('imagen', '');
            })
            ->orderBy('id')
            ->get(['id', 'nombre']);

        $this->info('Productos sin imagen: '.$productos->count().($dryRun ? ' (dry-run: no se sube ni guarda nada)' : ''));

        $bar = $this->output->createProgressBar($productos->count());
        $aplicados = 0;
        $revisar = [];

        foreach ($productos as $producto) {
            $candidato = $this->mejorCandidato($producto->nombre);

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
            usleep(150_000);
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
        $respuesta = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get(self::BASE_URL.'/imagenes/'.$candidato['archivo']);

        if (! $respuesta->successful() || ! str_starts_with((string) $respuesta->header('Content-Type'), 'image/')) {
            return false;
        }

        $ruta = 'productos/'.Str::slug($producto->nombre).'-'.$producto->id.'.jpg';
        Storage::disk('s3')->put($ruta, $respuesta->body());
        $producto->update(['imagen' => $ruta]);

        return true;
    }

    /**
     * @return array{id: string, archivo: string, nombre: string, score: float}|null
     */
    private function mejorCandidato(string $nombre): ?array
    {
        $candidatos = $this->buscar($nombre);

        if ($candidatos->isEmpty()) {
            // El nombre completo suele traer calificadores (tamaño, sabor, marca)
            // que no están en la web vieja: reintentamos con solo las primeras
            // palabras para tener al menos algún candidato para puntuar.
            $palabras = explode(' ', $nombre);
            $candidatos = $this->buscar(implode(' ', array_slice($palabras, 0, 2)));
        }

        /** @var array{id: string, archivo: string, nombre: string, score: float}|null $mejor */
        $mejor = $candidatos
            ->map(fn (array $c) => [...$c, 'score' => $this->similitud($nombre, $c['nombre'])])
            ->sortByDesc('score')
            ->first();

        return $mejor;
    }

    /**
     * @return Collection<int, array{id: string, archivo: string, nombre: string}>
     */
    private function buscar(string $query): Collection
    {
        $html = (string) Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get(self::BASE_URL.'/index.php', ['buscar' => $query])
            ->body();

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
