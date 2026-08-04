<?php

namespace App\Filament\Pages;

use App\Models\Pago;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Caja extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Finanzas';

    protected static ?string $navigationLabel = 'Caja';

    protected static ?string $title = 'Caja por período';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.caja';

    /**
     * Solo efectivo y transferencia se consideran "caja" (plata que entra
     * directo). MercadoPago/otro se muestran aparte: son medios que liquidan
     * por afuera y no deberían sumar al arqueo de caja física.
     */
    private const METODOS_EN_CAJA = ['efectivo', 'transferencia'];

    public ?string $desde = null;

    public ?string $hasta = null;

    public array $resumen = [];

    public bool $showResumen = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->desde = now()->startOfMonth()->format('Y-m-d');
        $this->hasta = now()->format('Y-m-d');

        $this->generar();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('desde')
                ->label('Desde')
                ->required(),
            Forms\Components\DatePicker::make('hasta')
                ->label('Hasta')
                ->required(),
        ])->columns(2);
    }

    public function generar(): void
    {
        if (! $this->desde || ! $this->hasta) {
            Notification::make()->title('Elegí las dos fechas')->warning()->send();

            return;
        }

        if ($this->hasta < $this->desde) {
            Notification::make()->title('La fecha "hasta" no puede ser anterior a "desde"')->warning()->send();

            return;
        }

        $pagos = Pago::vigentes()
            ->with('user')
            ->whereBetween('fecha', [$this->desde, $this->hasta])
            ->orderBy('fecha')
            ->get();

        $porMetodo = $pagos->groupBy('metodo');

        $this->resumen = [
            'totalCaja' => (float) $pagos->whereIn('metodo', self::METODOS_EN_CAJA)->sum('monto'),
            'porMetodo' => collect(Pago::METODOS)->map(fn (string $nombre, string $clave) => [
                'nombre' => $nombre,
                'total' => (float) $porMetodo->get($clave, collect())->sum('monto'),
                'cantidad' => $porMetodo->get($clave, collect())->count(),
                'incluido' => in_array($clave, self::METODOS_EN_CAJA, true),
            ])->toArray(),
            'detalle' => $pagos->map(fn (Pago $p) => [
                'fecha' => $p->fecha->format('d/m/Y'),
                'metodo' => Pago::METODOS[$p->metodo],
                'monto' => (float) $p->monto,
                'cliente' => $p->user?->name,
                'pedido_id' => $p->pedido_id,
                'notas' => $p->notas,
            ])->toArray(),
        ];

        $this->showResumen = true;
    }
}
