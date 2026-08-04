<?php

namespace App\Services;

use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pone el catálogo de la web a tono con la lista de precios definitiva.
 *
 * El cliente cambia nombres y marcas sin avisar, así que un producto que en la
 * lista aparece con otro nombre no es un producto nuevo: es el mismo. Si se
 * importara sin más, quedaría duplicado y el viejo sin foto.
 *
 * Clasifica cada producto de la web en:
 *  - se queda        : está igual en la lista
 *  - cambió de marca : mismo nombre, otra marca
 *  - cambió de nombre: nombre muy parecido dentro de la misma marca
 *  - se da de baja   : no aparece de ninguna forma
 *
 * La baja es lógica (activo = false): el producto queda en la base con su foto
 * y su historial de pedidos, sólo deja de mostrarse.
 */
class SincronizarCatalogo
{
    /** Qué tan parecido tiene que ser un nombre para darlo por el mismo. */
    private const PARECIDO_MINIMO = 88;

    public function __construct(private ProductImportService $importador) {}

    /**
     * @return array{cambiosDeMarca: list<array<string, mixed>>, cambiosDeNombre: list<array<string, mixed>>, bajas: list<array<string, mixed>>, dudosos: list<array<string, mixed>>, sinCambios: int}
     */
    public function analizar(string $archivo, int $filaEncabezados = 5): array
    {
        $filas = $this->importador->readFile($archivo, $filaEncabezados)
            ->filter(fn ($f) => ! empty($f['Nombre']) && ! empty($f['Marca']));

        $enLista = [];
        $porNombre = [];
        foreach ($filas as $f) {
            $enLista[$this->clave($f['Nombre'], $f['Marca'])] = true;
            $porNombre[$this->normalizar($f['Nombre'])][] = trim($f['Marca']);
        }
        $nombresLista = array_keys($porNombre);

        $resultado = ['cambiosDeMarca' => [], 'cambiosDeNombre' => [], 'bajas' => [], 'sinCambios' => 0];

        foreach (Producto::activos()->with('marca')->get() as $producto) {
            $marca = $producto->marca->nombre ?? '';

            if (isset($enLista[$this->clave($producto->nombre, $marca)])) {
                $resultado['sinCambios']++;

                continue;
            }

            $normalizado = $this->normalizar($producto->nombre);

            // Mismo nombre en la lista, pero bajo otra marca.
            if (isset($porNombre[$normalizado])) {
                $resultado['cambiosDeMarca'][] = [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'marcaVieja' => $marca,
                    'marcaNueva' => $porNombre[$normalizado][0],
                ];

                continue;
            }

            [$parecido, $pct] = $this->masParecido($normalizado, $nombresLista);

            // El cambio de nombre sólo se acepta dentro de la misma marca: si
            // además cambió de marca, hay demasiado margen para equivocarse.
            if ($parecido !== null && $pct >= self::PARECIDO_MINIMO
                && $this->normalizar($porNombre[$parecido][0]) === $this->normalizar($marca)) {
                $candidatos[] = [
                    'id' => $producto->id,
                    'marca' => $marca,
                    'nombreViejo' => $producto->nombre,
                    'destino' => $parecido,
                    'nombreNuevo' => $this->nombreReal($filas, $parecido),
                    'parecido' => (int) round($pct),
                ];

                continue;
            }

            $resultado['bajas'][] = ['id' => $producto->id, 'nombre' => $producto->nombre, 'marca' => $marca];
        }

        [$resultado['cambiosDeNombre'], $dudosos] = $this->depurarRenombres($candidatos ?? []);

        // Un renombre descartado es, por definición, un producto que no está en
        // la lista y cuyo parecido resultó ser otro producto: se da de baja como
        // cualquier otro. Se informa aparte para dejar constancia de por qué no
        // se renombró.
        $resultado['dudosos'] = $dudosos;

        foreach ($dudosos as $d) {
            $resultado['bajas'][] = ['id' => $d['id'], 'nombre' => $d['nombreViejo'], 'marca' => $d['marca']];
        }

        return $resultado;
    }

    /**
     * Saca los renombres que no son tales.
     *
     * El parecido por sí solo confunde variantes: "mermelada de frutilla y
     * durazno" se parece un 92% a "frutilla y damasco", pero son dos productos
     * distintos. Dos señales lo delatan:
     *
     *  - dos productos de la web apuntan al mismo nombre de la lista;
     *  - el nombre de destino ya existe en la web para esa misma marca, así que
     *    el producto que estamos mirando es otro.
     *
     * @param  list<array<string, mixed>>  $candidatos
     * @return array{list<array<string, mixed>>, list<array<string, mixed>>}
     */
    private function depurarRenombres(array $candidatos): array
    {
        $cuantosApuntan = array_count_values(array_column($candidatos, 'destino'));

        $yaExisteEnLaWeb = [];
        foreach (Producto::activos()->with('marca')->get() as $p) {
            $yaExisteEnLaWeb[$this->clave($p->nombre, $p->marca->nombre ?? '')] = true;
        }

        $buenos = [];
        $dudosos = [];

        foreach ($candidatos as $c) {
            if ($cuantosApuntan[$c['destino']] > 1) {
                $c['motivo'] = 'otro producto apunta al mismo nombre';
                $dudosos[] = $c;

                continue;
            }

            if (isset($yaExisteEnLaWeb[$this->clave($c['nombreNuevo'], $c['marca'])])) {
                $c['motivo'] = 'ese nombre ya existe en la web: son dos productos distintos';
                $dudosos[] = $c;

                continue;
            }

            $buenos[] = $c;
        }

        return [$buenos, $dudosos];
    }

    /**
     * @param  array{cambiosDeMarca: list<array<string, mixed>>, cambiosDeNombre: list<array<string, mixed>>, bajas: list<array<string, mixed>>, dudosos?: list<array<string, mixed>>, sinCambios: int}  $plan
     * @return array{marcas: int, nombres: int, bajas: int, duplicados: int}
     */
    public function aplicar(array $plan): array
    {
        $hechos = ['marcas' => 0, 'nombres' => 0, 'bajas' => 0];

        DB::transaction(function () use ($plan, &$hechos) {
            foreach ($plan['cambiosDeMarca'] as $cambio) {
                $marca = Marca::withTrashed()->where('nombre', $cambio['marcaNueva'])->first();

                if (! $marca) {
                    $marca = Marca::create(['nombre' => $cambio['marcaNueva']]);
                } elseif ($marca->trashed()) {
                    $marca->restore();
                }

                Producto::whereKey($cambio['id'])->update(['marca_id' => $marca->id]);
                $hechos['marcas']++;
            }

            foreach ($plan['cambiosDeNombre'] as $cambio) {
                // Sin tocar el slug: la dirección web del producto sigue siendo
                // la misma, así que los enlaces que ya circulan no se rompen.
                Producto::whereKey($cambio['id'])->update(['nombre' => $cambio['nombreNuevo']]);
                $hechos['nombres']++;
            }

            foreach (array_chunk(array_column($plan['bajas'], 'id'), 200) as $ids) {
                Producto::whereIn('id', $ids)->update(['activo' => false]);
                $hechos['bajas'] += count($ids);
            }

            $hechos['duplicados'] = $this->unificarDuplicados();
        });

        return $hechos;
    }

    /**
     * Deja un solo producto activo por nombre y marca.
     *
     * Mover productos de marca puede juntar dos que antes estaban separados
     * (los yogures Coco Iogo estaban duplicados en "Crudda" y en "QU (Crudda)",
     * y los dos van a "QU (Coco Iogo)"). Se conserva el que tiene foto -- y
     * entre esos, el más viejo, que es el que acumula el historial de pedidos.
     *
     * @return int cuántos quedaron dados de baja
     */
    private function unificarDuplicados(): int
    {
        $bajas = 0;

        $repetidos = Producto::activos()
            ->selectRaw('nombre, marca_id')
            ->groupBy('nombre', 'marca_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($repetidos as $repetido) {
            $productos = Producto::activos()
                ->where('nombre', $repetido->nombre)
                ->where('marca_id', $repetido->marca_id)
                ->get()
                ->sortBy(fn (Producto $p) => [blank($p->imagen) ? 1 : 0, $p->id])
                ->values();

            foreach ($productos->skip(1) as $sobrante) {
                $sobrante->update(['activo' => false]);
                $bajas++;
            }
        }

        return $bajas;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    private function nombreReal($filas, string $normalizado): string
    {
        foreach ($filas as $f) {
            if ($this->normalizar($f['Nombre']) === $normalizado) {
                return trim($f['Nombre']);
            }
        }

        return $normalizado;
    }

    /**
     * @param  list<string>  $candidatos
     * @return array{string|null, float}
     */
    private function masParecido(string $nombre, array $candidatos): array
    {
        $mejor = null;
        $mejorPct = 0.0;

        foreach ($candidatos as $candidato) {
            // Descarta de entrada los de largo muy distinto: acelera y evita
            // emparejar un nombre corto con uno larguísimo.
            if (abs(strlen($candidato) - strlen($nombre)) > 12) {
                continue;
            }

            similar_text($nombre, $candidato, $pct);

            if ($pct > $mejorPct) {
                $mejorPct = $pct;
                $mejor = $candidato;
            }
        }

        return [$mejor, $mejorPct];
    }

    private function clave(string $nombre, string $marca): string
    {
        return $this->normalizar($nombre).'|||'.$this->normalizar($marca);
    }

    private function normalizar(string $texto): string
    {
        return mb_strtolower(Str::ascii(trim($texto)));
    }
}
