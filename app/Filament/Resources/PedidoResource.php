<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PedidoResource\Pages;
use App\Mail\PedidoEstadoMail;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Presentacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class PedidoResource extends Resource
{
    protected static ?string $model = Pedido::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Section::make('Pedido')->schema([
                    Forms\Components\Select::make('estado')
                        ->options(Pedido::ESTADOS)
                        ->required(),
                    Forms\Components\Placeholder::make('fecha')
                        ->content(fn ($record) => $record?->created_at?->format('d/m/Y H:i') ?? '-'),
                ])->columnSpan(1),

                Forms\Components\Section::make('Cliente')->schema([
                    Forms\Components\Placeholder::make('cliente_nombre')
                        ->label('Nombre')
                        ->content(fn ($record) => $record?->datos_cliente['nombre'] ?? $record?->user?->name ?? '-'),
                    Forms\Components\Placeholder::make('cliente_negocio')
                        ->label('Negocio')
                        ->content(fn ($record) => $record?->datos_cliente['negocio'] ?? '-')
                        ->visible(fn ($record) => ! empty($record?->datos_cliente['negocio'])),
                    Forms\Components\Placeholder::make('cliente_contacto')
                        ->label('Contacto')
                        ->content(fn ($record) => ($record?->datos_cliente['celular'] ?? '').' · '.($record?->datos_cliente['email'] ?? '')),
                    Forms\Components\Placeholder::make('cliente_direccion')
                        ->label('Dirección')
                        ->content(fn ($record) => ($record?->datos_cliente['direccion'] ?? '').', '.($record?->datos_cliente['ciudad'] ?? '')),
                    Forms\Components\Placeholder::make('cliente_entrega')
                        ->label('Entrega')
                        ->content(fn ($record) => ($record?->datos_cliente['entrega'] ?? 'envio') === 'retiro' ? 'Retiro en local' : 'Envío a domicilio'),
                    Forms\Components\Placeholder::make('cliente_notas')
                        ->label('Notas')
                        ->content(fn ($record) => $record?->datos_cliente['notas'] ?? '-')
                        ->visible(fn ($record) => ! empty($record?->datos_cliente['notas'])),
                ])->columnSpan(2),
            ]),

            Forms\Components\Section::make('Productos del pedido')
                ->description('Podés modificar cantidades, eliminar o agregar productos antes de confirmar.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('presentacion_id')
                                ->label('Producto')
                                ->options(function () {
                                    return Presentacion::with('producto.marca')
                                        ->activos()
                                        ->whereHas('producto')
                                        ->get()
                                        ->mapWithKeys(fn ($p) => [
                                            $p->id => "{$p->producto->nombre} — {$p->unidad} (".($p->producto->marca->nombre ?? 'Sin marca').") \${$p->precio}",
                                        ]);
                                })
                                ->searchable()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        $p = Presentacion::find($state);
                                        if ($p) {
                                            $set('precio_unitario', $p->precio_final);
                                            $set('subtotal', $p->precio_final);
                                        }
                                    }
                                })
                                ->columnSpan(3),
                            Forms\Components\TextInput::make('cantidad')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->minValue(1)
                                ->reactive()
                                ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                    $precio = (float) $get('precio_unitario');
                                    $set('subtotal', round($precio * (int) $state, 2));
                                })
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('precio_unitario')
                                ->label('Precio unit.')
                                ->numeric()
                                ->prefix('$')
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                    $cantidad = (int) $get('cantidad');
                                    $set('subtotal', round((float) $state * $cantidad, 2));
                                })
                                ->columnSpan(1)
                                ->visible(fn () => auth()->user()?->isAdmin()),
                            Forms\Components\TextInput::make('subtotal')
                                ->numeric()
                                ->prefix('$')
                                ->disabled()
                                ->dehydrated()
                                ->columnSpan(1)
                                ->visible(fn () => auth()->user()?->isAdmin()),
                        ])
                        ->columns(6)
                        ->addActionLabel('Agregar producto')
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->itemLabel(function (array $state): ?string {
                            if (empty($state['presentacion_id'])) {
                                return null;
                            }
                            $p = Presentacion::with('producto')->find($state['presentacion_id']);

                            return $p ? ($p->producto?->nombre ?? 'Producto eliminado')." ({$p->unidad})" : null;
                        })
                        ->collapsible(),
                ]),

            Forms\Components\Section::make('Total')->schema([
                Forms\Components\Placeholder::make('total_display')
                    ->label('Total del pedido')
                    ->content(fn ($record) => $record ? '$'.number_format($record->total, 2, ',', '.') : '-'),
            ])->visible(fn () => auth()->user()?->isAdmin()),

            Forms\Components\Section::make('Pagos')
                ->description('Registrá pagos parciales o totales del cliente.')
                ->schema([
                    Forms\Components\Placeholder::make('resumen_pagos')
                        ->label('')
                        ->content(function ($record) {
                            if (! $record) {
                                return '-';
                            }
                            $pagado = $record->total_pagado;
                            $saldo = $record->saldo;
                            $color = $saldo <= 0 ? 'color:green' : 'color:#ef4444';

                            return new HtmlString(
                                'Pagado: <strong>$'.number_format($pagado, 2, ',', '.').'</strong> · '.
                                "Saldo: <strong style='{$color}'>\$".number_format($saldo, 2, ',', '.').'</strong>'.
                                ($saldo <= 0 ? ' ✓ Pagado completo' : '')
                            );
                        }),
                    Forms\Components\Repeater::make('pagos')
                        ->relationship()
                        ->schema([
                            Forms\Components\DatePicker::make('fecha')
                                ->required()
                                ->default(now())
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('monto')
                                ->numeric()
                                ->required()
                                ->prefix('$')
                                ->columnSpan(1),
                            Forms\Components\Select::make('metodo')
                                ->options(Pago::METODOS)
                                ->required()
                                ->default('efectivo')
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('notas')
                                ->placeholder('Nota opcional')
                                ->columnSpan(1),
                        ])
                        ->columns(4)
                        ->addActionLabel('Registrar pago')
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->itemLabel(fn (array $state): ?string => ! empty($state['monto'])
                                ? '$'.number_format((float) $state['monto'], 0, ',', '.').' — '.(Pago::METODOS[$state['metodo'] ?? ''] ?? '')
                                : null
                        )
                        ->collapsible(),
                ])->visible(fn () => auth()->user()?->isAdmin()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('datos_cliente')
                    ->label('Cliente')
                    ->formatStateUsing(function ($state, $record) {
                        $nombre = $state['nombre'] ?? $record->user?->name ?? 'N/A';
                        $negocio = $state['negocio'] ?? null;

                        return $negocio ? "{$nombre} ({$negocio})" : $nombre;
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->where('datos_cliente->nombre', 'like', "%{$search}%")
                            ->orWhere('datos_cliente->negocio', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                    }),
                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'preparing' => 'info',
                        'shipped' => 'info',
                        'delivered' => 'success',
                        'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => Pedido::ESTADOS[$state] ?? $state),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Productos'),
                Tables\Columns\TextColumn::make('total')
                    ->money('ARS')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isAdmin()),
                Tables\Columns\TextColumn::make('datos_cliente.entrega')
                    ->label('Entrega')
                    ->formatStateUsing(fn ($state) => $state === 'retiro' ? 'Retiro' : 'Envío')
                    ->badge()
                    ->color(fn ($state) => $state === 'retiro' ? 'gray' : 'info'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->label('Fecha'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options(Pedido::ESTADOS),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('confirmar')
                        ->label('Confirmar')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Pedido $record) => $record->estado === 'pending')
                        ->action(function (Pedido $record) {
                            static::cambiarEstado($record, 'confirmed', 'confirmado');
                        }),
                    Tables\Actions\Action::make('preparar')
                        ->label('En preparación')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->color('info')
                        ->visible(fn (Pedido $record) => $record->estado === 'confirmed')
                        ->action(function (Pedido $record) {
                            static::cambiarEstado($record, 'preparing', 'en preparación');
                        }),
                    Tables\Actions\Action::make('enviar')
                        ->label('Marcar enviado')
                        ->icon('heroicon-o-truck')
                        ->color('info')
                        ->visible(fn (Pedido $record) => $record->estado === 'preparing')
                        ->action(function (Pedido $record) {
                            static::cambiarEstado($record, 'shipped', 'enviado');
                        }),
                    Tables\Actions\Action::make('entregar')
                        ->label('Marcar entregado')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (Pedido $record) => $record->estado === 'shipped')
                        ->action(function (Pedido $record) {
                            static::cambiarEstado($record, 'delivered', 'entregado');
                        }),
                    Tables\Actions\Action::make('cancelar')
                        ->label('Cancelar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Pedido $record) => ! in_array($record->estado, ['delivered', 'canceled']))
                        ->action(function (Pedido $record) {
                            static::cambiarEstado($record, 'canceled', 'cancelado');
                        }),
                ])->label('Estado')->icon('heroicon-o-arrow-path'),
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->color('success')
                    ->url(function (Pedido $record) {
                        $cel = $record->datos_cliente['celular'] ?? '';
                        $cel = preg_replace('/\D/', '', $cel);
                        if (! str_starts_with($cel, '54')) {
                            $cel = '54'.$cel;
                        }

                        return "https://wa.me/{$cel}?text=".urlencode("Hola! Sobre tu pedido #{$record->id} en VEGANLIFE.");
                    }, shouldOpenInNewTab: true)
                    ->visible(fn (Pedido $record) => ! empty($record->datos_cliente['celular'])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function cambiarEstado(Pedido $record, string $estado, string $label): void
    {
        if ($estado === 'canceled') {
            $record->restaurarStock();
        }

        $record->update(['estado' => $estado]);
        Notification::make()->title("Pedido #{$record->id} {$label}")->success()->send();

        $email = $record->datos_cliente['email'] ?? $record->user?->email;
        if ($email) {
            try {
                Mail::to($email)->send(new PedidoEstadoMail($record, $estado));
            } catch (\Throwable $e) {
            }
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPedidos::route('/'),
            'view' => Pages\ViewPedido::route('/{record}'),
            'edit' => Pages\EditPedido::route('/{record}/edit'),
        ];
    }
}
