<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComboResource\Pages;
use App\Models\Combo;
use App\Models\Presentacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ComboResource extends Resource
{
    protected static ?string $model = Combo::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 24;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del combo')->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('descripcion')
                    ->rows(3),
                Forms\Components\FileUpload::make('imagen')
                    ->image()
                    ->maxSize(2048)
                    ->directory('combos')
                    ->visibility('public'),
                Forms\Components\Toggle::make('activo')->default(true),
            ]),
            Forms\Components\Section::make('Ítems del combo')->schema([
                Forms\Components\Repeater::make('items')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('presentacion_id')
                            ->label('Presentación')
                            ->options(function () {
                                return Presentacion::with('producto')
                                    ->activos()
                                    ->whereHas('producto')
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [
                                        $p->id => "{$p->producto->nombre} — {$p->unidad} (\${$p->precio})",
                                    ]);
                            })
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('cantidad')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->minValue(1),
                    ])
                    ->columns(2)
                    ->addActionLabel('Agregar ítem'),
            ]),
            Forms\Components\Section::make('Precio')->schema([
                Forms\Components\Select::make('tipo_precio')
                    ->label('Tipo de precio')
                    ->options([
                        'descuento' => 'Descuento por porcentaje',
                        'manual' => 'Precio manual fijo',
                        'auto' => 'Suma de productos (sin descuento)',
                    ])
                    ->default(fn ($record) => $record?->precio_manual ? 'manual' : ($record?->descuento_porcentaje ? 'descuento' : 'auto'))
                    ->dehydrated(false)
                    ->reactive(),
                Forms\Components\TextInput::make('descuento_porcentaje')
                    ->label('Porcentaje de descuento')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(1)
                    ->maxValue(90)
                    ->visible(fn (Forms\Get $get) => $get('tipo_precio') === 'descuento')
                    ->helperText(fn ($record) => $record ? 'Precio sin descuento: $'.number_format($record->precio_calculado, 2, ',', '.') : ''),
                Forms\Components\TextInput::make('precio_manual')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->visible(fn (Forms\Get $get) => $get('tipo_precio') === 'manual'),
                Forms\Components\Placeholder::make('precio_auto')
                    ->label('Precio final')
                    ->content(fn ($record) => $record ? '$'.number_format($record->precio, 2, ',', '.') : 'Guardá el combo para ver el precio'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('imagen')->circular(),
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Ítems'),
                Tables\Columns\TextColumn::make('precio_manual')
                    ->money('ARS')
                    ->label('Precio')
                    ->default('Auto'),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCombos::route('/'),
            'create' => Pages\CreateCombo::route('/create'),
            'edit' => Pages\EditCombo::route('/{record}/edit'),
        ];
    }
}
