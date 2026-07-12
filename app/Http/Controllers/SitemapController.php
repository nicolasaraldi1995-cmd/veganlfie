<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('productos.index'), 'priority' => '0.9'],
            ['loc' => route('combos.index'), 'priority' => '0.8'],
            ['loc' => route('nuevos'), 'priority' => '0.7'],
            ['loc' => route('ofertas'), 'priority' => '0.7'],
        ]);

        $urls = $urls->concat(
            Producto::activos()->select('slug', 'updated_at')->get()->map(fn (Producto $p) => [
                'loc' => route('productos.show', $p->slug),
                'lastmod' => $p->updated_at?->toAtomString(),
                'priority' => '0.6',
            ])
        );

        $urls = $urls->concat(
            Marca::activos()->select('slug', 'updated_at')->get()->map(fn (Marca $m) => [
                'loc' => route('marcas.show', $m->slug),
                'lastmod' => $m->updated_at?->toAtomString(),
                'priority' => '0.5',
            ])
        );

        $urls = $urls->concat(
            Categoria::activos()->select('slug', 'updated_at')->get()->map(fn (Categoria $c) => [
                'loc' => route('categorias.show', $c->slug),
                'lastmod' => $c->updated_at?->toAtomString(),
                'priority' => '0.5',
            ])
        );

        $urls = $urls->concat(
            Combo::activos()->select('slug', 'updated_at')->get()->map(fn (Combo $c) => [
                'loc' => route('combos.index').'#combo-'.$c->slug,
                'lastmod' => $c->updated_at?->toAtomString(),
                'priority' => '0.5',
            ])
        );

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
