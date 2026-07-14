<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Resources\PedidoResource;
use App\Models\Pago;
use App\Models\Pedido;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPedido extends ViewRecord
{
    protected static string $resource = PedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('registrar_pago')
                ->label('Registrar pago')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => auth()->user()?->isAdmin())
                ->form([
                    Forms\Components\TextInput::make('monto')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->prefix('$')
                        ->autofocus(),
                    Forms\Components\Select::make('metodo')
                        ->options(Pago::METODOS)
                        ->required()
                        ->default('efectivo'),
                    Forms\Components\DatePicker::make('fecha')
                        ->required()
                        ->default(now()),
                    Forms\Components\TextInput::make('notas')
                        ->placeholder('Nota opcional'),
                ])
                ->action(function (array $data) {
                    $this->record->pagos()->create($data);
                    Notification::make()
                        ->title('Pago de $'.number_format($data['monto'], 0, ',', '.').' registrado')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('recordar_pago')
                ->label('Recordar pago por WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('warning')
                ->visible(fn () => auth()->user()?->isAdmin() && $this->record->saldo > 0 && ! empty($this->record->datos_cliente['celular']))
                ->url(function () {
                    $cel = preg_replace('/\D/', '', $this->record->datos_cliente['celular'] ?? '');
                    if (! str_starts_with($cel, '54')) {
                        $cel = '54'.$cel;
                    }
                    $nombre = $this->record->datos_cliente['nombre'] ?? '';
                    $saldo = number_format($this->record->saldo, 0, ',', '.');
                    $mensaje = "Hola {$nombre}! Te recordamos que tenés un saldo pendiente de \${$saldo} correspondiente a tu pedido #{$this->record->id} en VEGANLIFE. Cualquier consulta, quedamos a disposición. ¡Gracias!";

                    return "https://wa.me/{$cel}?text=".urlencode($mensaje);
                }, shouldOpenInNewTab: true),
            Actions\EditAction::make()->label('Modificar pedido'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Infolists\Components\Grid::make(3)->schema([
                Infolists\Components\Section::make('Pedido')->schema([
                    Infolists\Components\TextEntry::make('id')->label('Número')->prefix('#'),
                    Infolists\Components\TextEntry::make('estado')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => Pedido::ESTADOS[$state] ?? $state)
                        ->color(fn (string $state) => match ($state) {
                            'pending' => 'warning',
                            'confirmed' => 'success',
                            'preparing' => 'info',
                            'shipped' => 'info',
                            'delivered' => 'success',
                            'canceled' => 'danger',
                            default => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Fecha')
                        ->dateTime('d/m/Y H:i'),
                ])->columnSpan(1),

                Infolists\Components\Section::make('Cliente')->schema([
                    Infolists\Components\TextEntry::make('datos_cliente.nombre')->label('Nombre'),
                    Infolists\Components\TextEntry::make('datos_cliente.negocio')->label('Negocio')
                        ->visible(fn ($record) => ! empty($record->datos_cliente['negocio'])),
                    Infolists\Components\TextEntry::make('datos_cliente.celular')->label('Celular'),
                    Infolists\Components\TextEntry::make('datos_cliente.email')->label('Email'),
                    Infolists\Components\TextEntry::make('datos_cliente.direccion')->label('Dirección'),
                    Infolists\Components\TextEntry::make('datos_cliente.ciudad')->label('Ciudad'),
                    Infolists\Components\TextEntry::make('datos_cliente.entrega')->label('Entrega')
                        ->formatStateUsing(fn ($state) => $state === 'retiro' ? 'Retiro en local' : 'Envío a domicilio'),
                ])->columnSpan(2)->columns(2),

                Infolists\Components\Section::make('Notas del cliente')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->schema([
                        Infolists\Components\TextEntry::make('datos_cliente.notas')
                            ->label('')
                            ->size('lg')
                            ->weight('medium')
                            ->color('warning'),
                    ])
                    ->visible(fn ($record) => ! empty($record->datos_cliente['notas']))
                    ->columnSpanFull(),
            ]),

            Infolists\Components\Section::make('Productos')->schema([
                Infolists\Components\ViewEntry::make('items_table')
                    ->view('filament.infolists.pedido-items'),
            ]),

            Infolists\Components\Section::make('Pagos')
                ->schema([
                    Infolists\Components\ViewEntry::make('pagos_table')
                        ->view('filament.infolists.pedido-pagos'),
                ])
                ->visible(fn () => auth()->user()?->isAdmin()),
        ]);
    }
}
