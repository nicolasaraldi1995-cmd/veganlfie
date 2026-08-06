<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrecioResource\Pages;
use App\Models\Presentacion;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Todos los precios de un producto en una sola línea.
 *
 * Antes, para saber a cuánto se compraba una mozzarella había que abrir el
 * producto, abrir la presentación y recién ahí se veían costo, descuento y
 * margen. Cuando un proveedor manda un aumento eso son veinte clics por
 * artículo. Acá se escribe "mozzarella" y salen todas con sus números.
 *
 * Es la misma tabla que Stock (presentaciones), con otras columnas: Stock es
 * del operador y no puede ver plata, ésta es del dueño y es sólo plata.
 */
class PrecioResource extends Resource
{
    protected static ?string $model = Presentacion::class;

    protected static ?string $slug = 'precios';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Precios';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'Precios';

    protected static ?string $pluralModelLabel = 'Precios';

    protected static ?string $modelLabel = 'precio';

    /**
     * Del dueño y de nadie más. Acá está el costo de cada artículo, que es lo
     * único que el panel esconde del operador de punta a punta.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->wrap()
                    ->width('26%')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('producto.marca.nombre')
                    ->label('Marca')
                    ->wrap()
                    ->width('15%')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unidad')
                    ->label('Unidad')
                    ->sortable(),
                Tables\Columns\TextInputColumn::make('precio_costo')
                    ->label('Costo')
                    ->type('number')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->afterStateUpdated(fn (Presentacion $record) => self::rehacerElPrecio($record))
                    ->sortable(),
                Tables\Columns\TextInputColumn::make('descuento_porcentaje')
                    ->label('Desc. prov. %')
                    ->type('number')
                    ->rules(['nullable', 'numeric', 'min:0', 'max:100'])
                    ->afterStateUpdated(fn (Presentacion $record) => self::rehacerElPrecio($record)),
                Tables\Columns\TextInputColumn::make('margen_porcentaje')
                    ->label('Margen %')
                    ->type('number')
                    ->rules(['nullable', 'numeric', 'min:-99', 'max:500'])
                    ->afterStateUpdated(fn (Presentacion $record) => self::rehacerElPrecio($record)),
                Tables\Columns\IconColumn::make('iva')
                    ->label('IVA')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('precio')
                    ->label('Precio venta')
                    ->money('ARS')
                    ->weight('bold')
                    ->sortable()
                    ->description(fn (Presentacion $record) => $record->precio_costo > 0 ? null : 'sin costo cargado'),
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
                // Para ir completando lo que falta sin tener que adivinar dónde
                // está: hoy no hay ni un costo cargado en todo el catálogo.
                Tables\Filters\TernaryFilter::make('con_costo')
                    ->label('Costo cargado')
                    ->queries(
                        true: fn ($q) => $q->where('precio_costo', '>', 0),
                        false: fn ($q) => $q->where(fn ($s) => $s->whereNull('precio_costo')->orWhere('precio_costo', '<=', 0)),
                    ),
            ])
            ->defaultSort('producto.nombre')
            // Igual que el resto del panel: sin "Todos", que con 2161 filas deja
            // la pantalla colgada y encima queda guardado en la sesión.
            ->paginated([25, 50, 100])
            ->bulkActions([
                Tables\Actions\BulkAction::make('aumentar')
                    ->label('Aumentar precios %')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('porcentaje')
                            ->label('Porcentaje')
                            ->numeric()
                            ->required()
                            ->minValue(-99)
                            ->maxValue(500)
                            ->suffix('%')
                            ->helperText('Positivo sube, negativo baja. Para una marca entera: filtrá por la marca y marcá todos.'),
                    ])
                    ->modalSubmitActionLabel('Aplicar')
                    ->action(fn ($records, array $data) => self::aumentar($records, (float) $data['porcentaje']))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /**
     * Sube (o baja) el costo y deja el precio de venta en línea con él.
     *
     * En las que tienen costo cargado se mueve el costo y el precio se rehace
     * con la cuenta de siempre, así los dos números siguen contando la misma
     * historia. En las que no lo tienen -- que hoy son todas-- se mueve el
     * precio de venta directamente, que es lo que hace "Actualizar precios".
     */
    private static function aumentar(iterable $records, float $porcentaje): void
    {
        $factor = 1 + $porcentaje / 100;
        $desdeElCosto = 0;
        $soloElPrecio = 0;

        foreach ($records as $presentacion) {
            if ((float) $presentacion->precio_costo > 0) {
                $presentacion->precio_costo = round((float) $presentacion->precio_costo * $factor, 2);
                $desdeElCosto++;
            } else {
                $soloElPrecio++;
            }

            self::rehacerElPrecio($presentacion, round((float) $presentacion->precio * $factor, 2));
        }

        $signo = $porcentaje >= 0 ? '+' : '';
        $detalle = $desdeElCosto > 0 && $soloElPrecio > 0
            ? "{$desdeElCosto} desde el costo, {$soloElPrecio} sobre el precio de venta"
            : ($desdeElCosto > 0 ? "{$desdeElCosto} desde el costo" : "{$soloElPrecio} sobre el precio de venta (no tienen costo cargado)");

        Notification::make()
            ->title("Precios actualizados {$signo}{$porcentaje}%")
            ->body($detalle)
            ->success()
            ->send();
    }

    /**
     * Rehace el precio de venta a partir de costo, descuento y margen, y guarda.
     *
     * Si faltan costo o margen no hay cuenta que hacer: queda el precio que ya
     * tenía, salvo que quien llama traiga uno de repuesto.
     */
    private static function rehacerElPrecio(Presentacion $presentacion, ?float $siNoSePuedeCalcular = null): void
    {
        $precio = Presentacion::calcularPrecio(
            $presentacion->precio_costo,
            $presentacion->margen_porcentaje,
            $presentacion->descuento_porcentaje,
            (bool) $presentacion->iva,
        ) ?? $siNoSePuedeCalcular;

        if ($precio !== null) {
            $presentacion->precio = $precio;
        }

        // Si había una oferta por porcentaje, su precio quedaba calculado sobre
        // el precio viejo: la oferta seguía mostrando el número de antes del
        // aumento hasta que alguien la tocara a mano.
        if ($presentacion->oferta_porcentaje) {
            $presentacion->oferta_precio = round(
                (float) $presentacion->precio * (1 - (float) $presentacion->oferta_porcentaje / 100),
                2
            );
        }

        $presentacion->save();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrecios::route('/'),
        ];
    }
}
