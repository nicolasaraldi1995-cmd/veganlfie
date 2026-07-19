<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductoResource\Pages;
use App\Models\Marca;
use App\Models\Producto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Producto')->tabs([
                Forms\Components\Tabs\Tab::make('Datos')->schema([
                    Forms\Components\TextInput::make('nombre')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('marca_id')
                        ->relationship('marca', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('nombre')->required(),
                        ]),
                    Forms\Components\Select::make('categoria_id')
                        ->relationship('categoria', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('nombre')->required(),
                        ]),
                    Forms\Components\Textarea::make('descripcion')
                        ->rows(3),
                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\Toggle::make('sin_tacc')->label('Sin TACC'),
                        Forms\Components\Toggle::make('frio')->label('Frío'),
                        Forms\Components\Toggle::make('congelado'),
                        Forms\Components\Toggle::make('nuevo'),
                    ]),
                    Forms\Components\Toggle::make('activo')->default(true),
                ]),
                Forms\Components\Tabs\Tab::make('Presentaciones')->schema([
                    Forms\Components\Repeater::make('presentaciones')
                        ->relationship()
                        ->schema([
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('unidad')
                                    ->required()
                                    ->placeholder('ej: 500gr'),
                                Forms\Components\TextInput::make('sku')
                                    ->placeholder('Código opcional'),
                                Forms\Components\TextInput::make('precio')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->prefix('$')
                                    ->visible(fn () => auth()->user()?->isAdmin()),
                                Forms\Components\TextInput::make('stock')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->default(0),
                            ]),
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('precio_costo')
                                    ->label('Precio de costo')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('$')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalcularPrecio($get, $set)),
                                Forms\Components\TextInput::make('descuento_porcentaje')
                                    ->label('Descuento proveedor')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->afterStateHydrated(fn (Forms\Components\TextInput $component, Forms\Get $get) => self::heredarDeMarcaSiVacio($component, $get, 'descuento_porcentaje'))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalcularPrecio($get, $set)),
                                Forms\Components\TextInput::make('margen_porcentaje')
                                    ->label('Margen de ganancia')
                                    ->numeric()
                                    ->minValue(-99)
                                    ->maxValue(500)
                                    ->suffix('%')
                                    ->afterStateHydrated(fn (Forms\Components\TextInput $component, Forms\Get $get) => self::heredarDeMarcaSiVacio($component, $get, 'margen_porcentaje'))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalcularPrecio($get, $set))
                                    ->helperText('Completá costo y margen para calcular el precio de arriba solo.'),
                                Forms\Components\Toggle::make('iva')
                                    ->label('IVA (21%)')
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalcularPrecio($get, $set)),
                            ])->visible(fn () => auth()->user()?->isAdmin()),
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('oferta_porcentaje')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(90)
                                    ->suffix('%')
                                    ->label('Oferta %')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalcularOferta($get, $set)),
                                Forms\Components\TextInput::make('oferta_precio')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('$')
                                    ->label('Precio oferta'),
                                Forms\Components\DatePicker::make('oferta_inicio')
                                    ->label('Inicio oferta'),
                                Forms\Components\DatePicker::make('oferta_fin')
                                    ->label('Fin oferta'),
                            ])->visible(fn () => auth()->user()?->isAdmin()),
                            Forms\Components\FileUpload::make('imagen')
                                ->image()
                                ->maxSize(2048)
                                ->directory('presentaciones')
                                ->visibility('public')
                                ->imagePreviewHeight('100')
                                ->label('Imagen'),
                            Forms\Components\Toggle::make('activo')->default(true),
                        ])
                        ->defaultItems(1)
                        ->addActionLabel('Agregar presentación')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['unidad'] ?? null),
                ]),
                Forms\Components\Tabs\Tab::make('Imagen')->schema([
                    Forms\Components\FileUpload::make('imagen')
                        ->image()
                        ->maxSize(2048)
                        ->directory('productos')
                        ->visibility('public')
                        ->imagePreviewHeight('200'),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    private static function heredarDeMarcaSiVacio(Forms\Components\TextInput $component, Forms\Get $get, string $campo): void
    {
        if (filled($component->getState())) {
            return;
        }

        $component->state(Marca::find($get('../../marca_id'))?->{$campo});
    }

    private static function recalcularPrecio(Forms\Get $get, Forms\Set $set): void
    {
        $costo = $get('precio_costo');
        $margen = $get('margen_porcentaje');

        if ($costo === null || $costo === '' || $margen === null || $margen === '') {
            return;
        }

        $descuento = (float) ($get('descuento_porcentaje') ?? 0);

        $precio = (float) $costo * (1 - $descuento / 100) * (1 + (float) $margen / 100);

        if ($get('iva')) {
            $precio *= 1.21;
        }

        $set('precio', round($precio, 2));
    }

    private static function recalcularOferta(Forms\Get $get, Forms\Set $set): void
    {
        $porcentaje = $get('oferta_porcentaje');
        $precio = (float) ($get('precio') ?? 0);

        if ($porcentaje === null || $porcentaje === '' || $precio <= 0) {
            return;
        }

        $set('oferta_precio', round($precio * (1 - (float) $porcentaje / 100), 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('imagen')
                    ->circular(),
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('marca.nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('categoria.nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('presentaciones_count')
                    ->counts('presentaciones')
                    ->label('Pres.')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('sin_tacc')
                    ->label('Sin TACC'),
                Tables\Columns\ToggleColumn::make('frio')
                    ->label('Frío'),
                Tables\Columns\ToggleColumn::make('congelado'),
                Tables\Columns\ToggleColumn::make('nuevo'),
                Tables\Columns\ToggleColumn::make('activo'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marca_id')
                    ->relationship('marca', 'nombre')
                    ->searchable()
                    ->preload()
                    ->label('Marca'),
                Tables\Filters\SelectFilter::make('categoria_id')
                    ->relationship('categoria', 'nombre')
                    ->searchable()
                    ->preload()
                    ->label('Categoría'),
                Tables\Filters\TernaryFilter::make('sin_tacc')->label('Sin TACC'),
                Tables\Filters\TernaryFilter::make('frio')->label('Frío'),
                Tables\Filters\TernaryFilter::make('congelado'),
                Tables\Filters\TernaryFilter::make('nuevo'),
                Tables\Filters\TernaryFilter::make('activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('marcar_sin_tacc')
                        ->label('Marcar Sin TACC')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['sin_tacc' => true])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('quitar_sin_tacc')
                        ->label('Quitar Sin TACC')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['sin_tacc' => false])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('marcar_frio')
                        ->label('Marcar Frío')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['frio' => true])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('quitar_frio')
                        ->label('Quitar Frío')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['frio' => false])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('marcar_congelado')
                        ->label('Marcar Congelado')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['congelado' => true])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('quitar_congelado')
                        ->label('Quitar Congelado')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['congelado' => false])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('marcar_nuevo')
                        ->label('Marcar Nuevo')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['nuevo' => true])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('quitar_nuevo')
                        ->label('Quitar Nuevo')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['nuevo' => false])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProducto::route('/create'),
            'edit' => Pages\EditProducto::route('/{record}/edit'),
        ];
    }
}
