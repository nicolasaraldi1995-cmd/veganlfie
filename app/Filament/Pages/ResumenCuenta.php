<?php

namespace App\Filament\Pages;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Services\CuentaClienteService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ResumenCuenta extends Page implements Forms\Contracts\HasForms, HasActions
{
    use Forms\Concerns\InteractsWithForms;
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Resumen de cuenta';

    protected static ?string $title = 'Resumen de Cuenta';

    protected static ?int $navigationSort = 5;

    /**
     * Del dueño, como Caja, Gastos y Clientes: acá se ve cuánto debe y qué
     * celular tiene cada cliente, y desde el botón de cobrar se registra
     * plata. El operador carga pedidos, no maneja la cuenta corriente.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

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

    /**
     * De qué cliente es el cobro: el de la fila que se apretó en la lista de
     * los que deben, o el que se está mirando en el resumen de abajo.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function clienteDelCobro(array $arguments): ?User
    {
        return User::find($arguments['cliente'] ?? $this->cliente_id);
    }

    /**
     * Anota la plata que el cliente entrega a cuenta. No se ata a ningún pedido
     * puntual: entra como pago general y baja el saldo total, que es lo que se
     * está mirando en esta pantalla. Para saldar un pedido en particular está
     * el botón de pago del propio pedido.
     */
    public function registrarPagoAction(): Action
    {
        return Action::make('registrarPago')
            ->label('Cobrar')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->size('sm')
            ->modalHeading(fn (array $arguments) => 'Cobrarle a '.($this->clienteDelCobro($arguments)->name ?? 'cliente'))
            ->modalDescription(function (array $arguments): string {
                $cliente = $this->clienteDelCobro($arguments);
                $debe = $cliente ? app(CuentaClienteService::class)->saldoDe($cliente) : null;

                return 'Debe $'.number_format((float) $debe, 0, ',', '.').'.';
            })
            ->modalSubmitActionLabel('Registrar')
            // El monto viene con lo que debe: si paga todo, no hay nada que
            // tipear. Se calcula al abrir, para que valga igual desde la lista
            // de arriba que desde el resumen.
            ->fillForm(function (array $arguments): array {
                $cliente = $this->clienteDelCobro($arguments);
                $debe = $cliente ? app(CuentaClienteService::class)->saldoDe($cliente) : null;

                return [
                    'monto' => round(max(0, (float) $debe), 2),
                    'metodo' => 'efectivo',
                    'fecha' => now(),
                ];
            })
            ->form([
                Forms\Components\TextInput::make('monto')
                    ->label('Cuánto entrega')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->prefix('$')
                    ->autofocus(),
                Forms\Components\ToggleButtons::make('metodo')
                    ->label('Cómo pagó')
                    ->options(Pago::METODOS)
                    ->colors([
                        'efectivo' => 'success',
                        'transferencia' => 'info',
                        'mercadopago' => 'warning',
                        'otro' => 'gray',
                    ])
                    ->icons([
                        'efectivo' => 'heroicon-o-banknotes',
                        'transferencia' => 'heroicon-o-arrows-right-left',
                        'mercadopago' => 'heroicon-o-device-phone-mobile',
                        'otro' => 'heroicon-o-ellipsis-horizontal',
                    ])
                    ->inline()
                    ->required(),
                Forms\Components\DatePicker::make('fecha')
                    ->label('Fecha')
                    ->required(),
                Forms\Components\TextInput::make('notas')
                    ->label('Nota')
                    ->placeholder('Opcional'),
            ])
            ->action(function (array $arguments, array $data): void {
                $cliente = $this->clienteDelCobro($arguments);

                if (! $cliente) {
                    return;
                }

                Pago::create([
                    'user_id' => $cliente->id,
                    'monto' => $data['monto'],
                    'metodo' => $data['metodo'],
                    'fecha' => $data['fecha'],
                    'notas' => $data['notas'] ?? null,
                ]);

                // La lista de arriba siempre queda vieja: el cliente puede
                // desaparecer de ella si saldó todo. El resumen de abajo, sólo
                // si es justo el cliente que se está mirando.
                $this->cargarClientesConSaldo();

                if ($this->showResumen && (int) $this->cliente_id === $cliente->id) {
                    $this->verResumen();
                }

                Notification::make()
                    ->title('Pago de $'.number_format((float) $data['monto'], 0, ',', '.').' de '.$cliente->name)
                    ->success()
                    ->send();
            });
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
                    'metodo' => Pago::METODOS[$pg->metodo],
                    'notas' => $pg->notas,
                ])->toArray(),
            ])->toArray(),
        ];

        $this->showResumen = true;
    }
}
