<?php

namespace App\Filament\Pages;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Services\CuentaClienteService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ResumenCuenta extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Resumen de cuenta';

    protected static ?string $title = 'Resumen de Cuenta';

    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.pages.resumen-cuenta';

    public ?string $cliente_id = null;

    public array $resumen = [];

    public bool $showResumen = false;

    public array $clientesConSaldo = [];

    public function mount(): void
    {
        $this->cargarClientesConSaldo();
    }

    public function cargarClientesConSaldo(): void
    {
        $this->clientesConSaldo = app(CuentaClienteService::class)->resumenPorCliente()
            ->filter(fn (array $c) => $c['saldo'] > 0.009)
            ->sortBy('desde')
            ->values()
            ->map(fn (array $c) => [
                ...$c,
                'desde' => $c['desde']->format('d/m/Y'),
            ])
            ->toArray();
    }

    public function verClienteConSaldo(int $userId): void
    {
        $this->cliente_id = (string) $userId;
        $this->verResumen();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('cliente_id')
                ->label('Cliente')
                ->options(function () {
                    return User::whereHas('pedidos')
                        ->get()
                        ->mapWithKeys(fn ($u) => [
                            $u->id => $u->name.($u->negocio ? " ({$u->negocio})" : '')." — {$u->email}",
                        ]);
                })
                ->searchable()
                ->required(),
        ]);
    }

    public function verResumen(): void
    {
        if (! $this->cliente_id) {
            return;
        }

        $user = User::find($this->cliente_id);
        $pedidos = Pedido::where('user_id', $this->cliente_id)
            ->where('estado', '!=', 'canceled')
            ->with(['items.presentacion.producto', 'pagos'])
            ->orderByDesc('created_at')
            ->get();

        $pagosGenerales = Pago::where('user_id', $this->cliente_id)
            ->whereNull('pedido_id')
            ->orderByDesc('fecha')
            ->get();

        $totalPedidos = $pedidos->sum('total');
        $totalPagado = $pedidos->sum(fn ($p) => $p->pagos->sum('monto')) + $pagosGenerales->sum('monto');
        $saldoTotal = $totalPedidos - $totalPagado;

        $this->resumen = [
            'cliente' => [
                'nombre' => $user->name,
                'negocio' => $user->negocio,
                'email' => $user->email,
                'celular' => $user->celular,
            ],
            'totalPedidos' => $totalPedidos,
            'totalPagado' => $totalPagado,
            'saldoTotal' => $saldoTotal,
            'pagosGenerales' => $pagosGenerales->map(fn ($pg) => [
                'fecha' => $pg->fecha->format('d/m/Y'),
                'monto' => (float) $pg->monto,
                'metodo' => Pago::METODOS[$pg->metodo],
                'notas' => $pg->notas,
            ])->toArray(),
            'pedidos' => $pedidos->map(fn ($p) => [
                'id' => $p->id,
                'fecha' => $p->created_at->format('d/m/Y'),
                'estado' => Pedido::ESTADOS[$p->estado] ?? $p->estado,
                'total' => (float) $p->total,
                'pagado' => (float) $p->pagos->sum('monto'),
                'saldo' => (float) $p->total - $p->pagos->sum('monto'),
                'items_count' => $p->items->count(),
                'pagos' => $p->pagos->map(fn ($pg) => [
                    'fecha' => $pg->fecha->format('d/m/Y'),
                    'monto' => (float) $pg->monto,
                    'metodo' => Pago::METODOS[$pg->metodo] ?? $pg->metodo,
                    'notas' => $pg->notas,
                ])->toArray(),
            ])->toArray(),
        ];

        $this->showResumen = true;
    }
}
