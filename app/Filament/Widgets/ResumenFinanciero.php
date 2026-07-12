<?php

namespace App\Filament\Widgets;

use App\Models\Gasto;
use App\Models\Pedido;
use App\Models\Presentacion;
use App\Models\Producto;
use Filament\Widgets\Widget;

class ResumenFinanciero extends Widget
{
    protected static string $view = 'filament.widgets.resumen-financiero';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getData(): array
    {
        $hoy = now();

        $pedidosHoy = Pedido::whereDate('created_at', today())->where('estado', '!=', 'draft')->count();
        $ventasHoy = Pedido::whereDate('created_at', today())->where('estado', '!=', 'draft')->where('estado', '!=', 'canceled')->sum('total');

        $pedidosMes = Pedido::whereMonth('created_at', $hoy->month)->whereYear('created_at', $hoy->year)->where('estado', '!=', 'draft')->count();
        $ventasMes = Pedido::whereMonth('created_at', $hoy->month)->whereYear('created_at', $hoy->year)->where('estado', '!=', 'draft')->where('estado', '!=', 'canceled')->sum('total');

        $gastosMes = Gasto::whereMonth('fecha', $hoy->month)->whereYear('fecha', $hoy->year)->sum('monto');
        $gastosPorTipo = Gasto::whereMonth('fecha', $hoy->month)->whereYear('fecha', $hoy->year)
            ->selectRaw('tipo, sum(monto) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $balance = $ventasMes - $gastosMes;

        $pendientes = Pedido::where('estado', 'pending')->count();
        $productosActivos = Producto::activos()->count();
        $sinStock = Presentacion::where('stock', '<=', 0)->activos()->count();

        return compact(
            'pedidosHoy', 'ventasHoy',
            'pedidosMes', 'ventasMes',
            'gastosMes', 'gastosPorTipo', 'balance',
            'pendientes', 'productosActivos', 'sinStock'
        );
    }
}
