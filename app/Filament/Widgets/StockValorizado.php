<?php

namespace App\Filament\Widgets;

use App\Models\Presentacion;
use Filament\Widgets\Widget;

class StockValorizado extends Widget
{
    protected static string $view = 'filament.widgets.stock-valorizado';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getData(): array
    {
        $totalValor = Presentacion::where('activo', true)
            ->where('stock', '>', 0)
            ->selectRaw('SUM(stock * precio) as total')
            ->value('total') ?? 0;

        $porMarca = Presentacion::where('presentaciones.activo', true)
            ->where('stock', '>', 0)
            ->join('productos', 'presentaciones.producto_id', '=', 'productos.id')
            ->join('marcas', 'productos.marca_id', '=', 'marcas.id')
            ->selectRaw('marcas.nombre as marca, SUM(presentaciones.stock * presentaciones.precio) as valor, SUM(presentaciones.stock) as unidades')
            ->groupBy('marcas.nombre')
            ->orderByDesc('valor')
            ->take(15)
            ->get();

        $totalUnidades = Presentacion::where('activo', true)->where('stock', '>', 0)->sum('stock');

        return compact('totalValor', 'porMarca', 'totalUnidades');
    }
}
