<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductoResource\Pages;
use App\Models\Marca;
use App\Models\Presentacion;
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

    protected static ?int $navigationSort = 10;

    /**
     * ->image() a secas valida "mimetypes:image/*", y ahí adentro entra
     * image/svg+xml: un SVG lleva <script> y se serviría desde este dominio.
     * Se listan los formatos de foto, como ya hacía MarcaResource.
     */
    public const IMAGENES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

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
                                    ->default(0)
                                    ->prefix('$')
                                    ->visible(fn () => auth()->user()?->isAdmin())
                                    ->dehydratedWhenHidden(),
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
                                    ->lt('precio')
                                    ->validationMessages(['lt' => 'El precio de oferta tiene que ser menor al precio normal.'])
                                    ->prefix('$')
                                    ->label('Precio oferta'),
                                Forms\Components\DatePicker::make('oferta_inicio')
                                    ->label('Inicio oferta'),
                                Forms\Components\DatePicker::make('oferta_fin')
                                    ->label('Fin oferta'),
                            ])->visible(fn () => auth()->user()?->isAdmin()),
                            Forms\Components\FileUpload::make('imagen')
                                ->image()
                                ->acceptedFileTypes(self::IMAGENES)
                                ->maxSize(5120)
                                ->directory('presentaciones')
                                ->visibility('public')
                                ->imagePreviewHeight('100')
                                ->label('Imagen'),
                            Forms\Components\Toggle::make('activo')->default(true),
                        ])
                        // Los campos de plata están ocultos para el operador
                        // pero se dehidratan igual, así que su valor llegaba
                        // desde el navegador: con $wire.set se podía dejar el
                        // precio público de un producto en $1. Se repone acá,
                        // en el servidor.
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::soloElAdminPoneElPrecio($data, null))
                        ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, Presentacion $record) => self::soloElAdminPoneElPrecio($data, $record))
                        ->defaultItems(1)
                        ->addActionLabel('Agregar presentación')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['unidad'] ?? null),
                ]),
                Forms\Components\Tabs\Tab::make('Imagen')->schema([
                    Forms\Components\FileUpload::make('imagen')
                        ->image()
                        ->acceptedFileTypes(self::IMAGENES)
                        ->maxSize(5120)
                        ->directory('productos')
                        ->visibility('public')
                        ->imagePreviewHeight('200'),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    private static function heredarDeMarcaSiVacio(Forms\Components\TextInput $component, Forms\Get $get, string $campo): void
    {
        // Solo para el dueño. Al operador estos campos ni se le muestran, pero
        // el estado del formulario igual viaja a su navegador: este gancho le
        // volvía a poner el margen de la marca justo después de recortarlo.
        if (! (auth()->user()?->isAdmin() ?? false)) {
            return;
        }

        if (filled($component->getState())) {
            return;
        }

        $component->state(Marca::find($get('../../marca_id'))?->{$campo});
    }

    /**
     * Repone los valores de plata que el operador no tendría que poder tocar.
     * Los campos están ocultos para él, pero ocultar no es impedir: el estado
     * viaja al navegador y vuelve. Al admin no se le toca nada, que para eso
     * los ve y los edita.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function soloElAdminPoneElPrecio(array $data, ?Presentacion $guardada): array
    {
        if (auth()->user()?->isAdmin() ?? false) {
            return $data;
        }

        // Sobre una presentación que ya existe se repone lo que hay en la base.
        // Sobre una nueva queda en cero, y con precio cero no se publica (ver
        // Presentacion::scopeActivos): el dueño le pone el precio y recién ahí
        // aparece en la web. Antes salía a la venta a $0.
        foreach (['precio', 'precio_costo', 'margen_porcentaje', 'descuento_porcentaje', 'oferta_precio', 'oferta_porcentaje'] as $campo) {
            $data[$campo] = $guardada->{$campo} ?? ($campo === 'precio' ? 0 : null);
        }

        return $data;
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

        // Si ya había una oferta % cargada, su precio de oferta quedaba con
        // el valor viejo (calculado sobre el precio anterior) hasta que se
        // volviera a tocar "Oferta %" a mano.
        self::recalcularOferta($get, $set);
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
                    ->circular()
                    // Sin esto Filament le pregunta al disco si el archivo
                    // existe, una vez por fila. Con las imágenes en un bucket
                    // eso son 25 consultas por red sólo para dibujar la tabla, y
                    // la lista de productos se caía con un 504. Si alguna imagen
                    // faltara se ve rota, que para eso está el aviso de al lado.
                    ->checkFileExistence(false),
                // Marca visual para encontrar de un vistazo los productos que
                // quedaron sin foto (hay ~189 tras perderse el disco viejo).
                Tables\Columns\IconColumn::make('sin_imagen')
                    ->label('')
                    ->getStateUsing(fn (Producto $record) => blank($record->imagen))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('')
                    ->tooltip(fn (Producto $record) => blank($record->imagen) ? 'Sin foto' : null),
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
                Tables\Filters\Filter::make('sin_imagen')
                    ->label('Sin foto')
                    // En la web la tarjeta usa la foto de la presentación si el
                    // producto no tiene una propia, así que un producto sin foto
                    // propia igual puede verse bien. Acá se listan sólo los que
                    // no tienen foto en ningún lado, que son los que hay que
                    // completar de verdad.
                    ->query(fn ($query) => $query
                        ->where(fn ($q) => $q->whereNull('imagen')->orWhere('imagen', ''))
                        ->whereDoesntHave('presentaciones', fn ($q) => $q->whereNotNull('imagen')->where('imagen', '!=', '')))
                    ->toggle(),
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
