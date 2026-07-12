<?php

namespace App\Filament\Widgets;

use App\Models\Pedido;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PedidosRecientes extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Pedido::query()
                    ->where('estado', '!=', 'draft')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#'),
                Tables\Columns\TextColumn::make('datos_cliente')
                    ->label('Cliente')
                    ->formatStateUsing(fn ($state) => $state['nombre'] ?? 'N/A'),
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
                Tables\Columns\TextColumn::make('total')
                    ->money('ARS')
                    ->visible(fn () => auth()->user()?->isAdmin()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->heading('Pedidos recientes')
            ->paginated(false);
    }
}
