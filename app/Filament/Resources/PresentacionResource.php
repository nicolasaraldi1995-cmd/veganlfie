<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PresentacionResource\Pages;
use App\Models\Presentacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PresentacionResource extends Resource
{
    protected static ?string $model = Presentacion::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Stock';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 23;

    protected static ?string $pluralModelLabel = 'Stock';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('producto_nombre')
                ->label('Producto')
                ->disabled()
                ->dehydrated(false)
                ->default(fn ($record) => $record?->producto?->nombre),
            Forms\Components\TextInput::make('unidad')->disabled(),
            Forms\Components\TextInput::make('precio')->numeric()->prefix('$')
                ->visible(fn () => auth()->user()?->isAdmin()),
            Forms\Components\TextInput::make('stock')->numeric()->required()->autofocus(),
            Forms\Components\Toggle::make('activo'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->limit(35),
                Tables\Columns\TextColumn::make('producto.marca.nombre')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('producto.categoria.nombre')
                    ->label('Categoría')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unidad')
                    ->sortable(),
                Tables\Columns\TextColumn::make('precio')
                    ->money('ARS')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isAdmin()),
                Tables\Columns\TextInputColumn::make('stock')
                    ->type('number')
                    ->rules(['integer', 'min:0'])
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('activo'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marca')
                    ->relationship('producto.marca', 'nombre')
                    ->searchable()
                    ->preload()
                    ->label('Marca'),
                Tables\Filters\SelectFilter::make('categoria')
                    ->relationship('producto.categoria', 'nombre')
                    ->searchable()
                    ->preload()
                    ->label('Categoría'),
                Tables\Filters\TernaryFilter::make('con_stock')
                    ->label('Stock')
                    ->queries(
                        true: fn ($q) => $q->where('stock', '>', 0),
                        false: fn ($q) => $q->where('stock', '<=', 0),
                    ),
            ])
            ->defaultSort('producto.nombre')
            ->paginated([25, 50, 100])
            ->bulkActions([
                Tables\Actions\BulkAction::make('set_stock')
                    ->label('Asignar stock')
                    ->icon('heroicon-o-archive-box')
                    ->form([
                        Forms\Components\TextInput::make('stock')
                            ->label('Nuevo stock')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                    ])
                    ->action(function ($records, array $data) {
                        $records->each(fn ($r) => $r->update(['stock' => $data['stock']]));
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPresentaciones::route('/'),
        ];
    }
}
