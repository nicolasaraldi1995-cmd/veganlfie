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

    protected static ?string $navigationGroup = 'Promociones';

    protected static ?int $navigationSort = 21;

    /**
     * El middleware del panel ya frena a quien no es del equipo, pero eso
     * cubre la URL, no el componente. Filament revisa esto en cada hidratación,
     * así que el que intente invocarlo por dentro tampoco pasa.
     */
    public static function canAccess(): bool
    {
        $usuario = auth()->user();

        return (bool) ($usuario?->isAdmin() || $usuario?->isOperador());
    }

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
                    ->acceptedFileTypes(ProductoResource::IMAGENES)
                    ->maxSize(5120)
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
                                // Sin el precio para el operador: con abrir
                                // "Crear combo" se llevaba la lista entera.
                                // Mismo criterio que PedidoResource.
                                $mostrarPrecio = auth()->user()?->isAdmin();

                                return Presentacion::with('producto')
                                    ->activos()
                                    ->whereHas('producto')
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [
                                        $p->id => "{$p->producto->nombre} — {$p->unidad}".($mostrarPrecio ? " (\${$p->precio})" : ''),
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
                    ->default(fn ($record) => $record?->precio_manual !== null ? 'manual' : ($record?->descuento_porcentaje !== null ? 'descuento' : 'auto'))
                    ->dehydrated(false)
                    ->reactive()
                    // Al cambiar de tipo se limpia el precio del otro modo. Sin
                    // esto, pasar de "manual" a "descuento" dejaba el precio
                    // manual viejo escondido, y como le gana al porcentaje, el
                    // combo seguía cobrando el precio de antes.
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state !== 'manual') {
                            $set('precio_manual', null);
                        }
                        if ($state !== 'descuento') {
                            $set('descuento_porcentaje', null);
                        }
                    }),
                Forms\Components\TextInput::make('descuento_porcentaje')
                    ->label('Porcentaje de descuento')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(1)
                    ->maxValue(90)
                    ->visible(fn (Forms\Get $get) => $get('tipo_precio') === 'descuento')
                    // Aunque esté oculto se guarda: así el null que deja el
                    // cambio de tipo llega a la base y no queda el valor viejo.
                    ->dehydratedWhenHidden()
                    ->helperText(fn ($record) => $record ? 'Precio sin descuento: $'.number_format($record->precio_calculado, 2, ',', '.') : ''),
                Forms\Components\TextInput::make('precio_manual')
                    ->numeric()
                    // 0.01 y no 0: un precio manual de $0 publicaba el combo gratis.
                    ->minValue(0.01)
                    ->prefix('$')
                    ->visible(fn (Forms\Get $get) => $get('tipo_precio') === 'manual')
                    ->dehydratedWhenHidden(),
                Forms\Components\Placeholder::make('precio_auto')
                    ->label('Precio final')
                    ->content(fn ($record) => $record ? '$'.number_format($record->precio, 2, ',', '.') : 'Guardá el combo para ver el precio'),
            ])
                // Poner precios es del dueño, igual que Actualizar precios y
                // Ofertas masivas. El operador podía dejar un combo en $1 con
                // solo cambiar el desplegable a "precio manual", y así queda
                // publicado en la web.
                ->visible(fn () => auth()->user()?->isAdmin() ?? false),
        ]);
    }

    /**
     * Deja sólo el precio del modo elegido y borra el del otro. El desplegable
     * "tipo de precio" no se guarda (es virtual), así que sin esto un combo que
     * pasó de manual a descuento se quedaba con el precio manual viejo escondido
     * en la base -- y como le gana al porcentaje, seguía cobrando el de antes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function limpiarPrecioSegunTipo(array $data, ?string $tipo): array
    {
        if ($tipo !== 'manual') {
            $data['precio_manual'] = null;
        }
        if ($tipo !== 'descuento') {
            $data['descuento_porcentaje'] = null;
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('imagen')->circular()->checkFileExistence(false),
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
            // Sin esto Filament ofrece "Todos" en el desplegable, y la
            // elección queda guardada en la sesión: quien la toque deja la
            // pantalla colgada dos horas sin poder volver atrás.
            ->paginated([25, 50, 100])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->isAdmin() ?? false),
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
