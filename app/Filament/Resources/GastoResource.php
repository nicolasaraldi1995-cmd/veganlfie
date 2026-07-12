<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GastoResource\Pages;
use App\Models\Gasto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GastoResource extends Resource
{
    protected static ?string $model = Gasto::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finanzas';

    protected static ?string $navigationLabel = 'Gastos';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('concepto')
                ->required()
                ->maxLength(255)
                ->placeholder('Ej: Pago comisión zona norte, Compra mercadería Chía Graal'),
            Forms\Components\Select::make('tipo')
                ->options(Gasto::TIPOS)
                ->required(),
            Forms\Components\TextInput::make('monto')
                ->numeric()
                ->required()
                ->prefix('$')
                ->minValue(0),
            Forms\Components\DatePicker::make('fecha')
                ->required()
                ->default(now()),
            Forms\Components\Textarea::make('notas')
                ->rows(2)
                ->placeholder('Detalle opcional'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('concepto')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Gasto::TIPOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'comisionista' => 'warning',
                        'proveedor' => 'info',
                        'logistica' => 'gray',
                        'otro' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('monto')
                    ->money('ARS')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cargado')
                    ->dateTime('d/m H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->options(Gasto::TIPOS),
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde'),
                        Forms\Components\DatePicker::make('hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn ($q, $d) => $q->whereDate('fecha', '>=', $d))
                            ->when($data['hasta'], fn ($q, $d) => $q->whereDate('fecha', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGastos::route('/'),
            'create' => Pages\CreateGasto::route('/create'),
            'edit' => Pages\EditGasto::route('/{record}/edit'),
        ];
    }
}
