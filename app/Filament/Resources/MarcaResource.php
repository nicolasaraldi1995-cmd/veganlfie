<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarcaResource\Pages;
use App\Models\Marca;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MarcaResource extends Resource
{
    protected static ?string $model = Marca::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 13;

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
            Forms\Components\TextInput::make('nombre')
                ->required()
                ->maxLength(255),
            Forms\Components\FileUpload::make('logo')
                ->image()
                // Sin SVG: el servidor no lo puede encuadrar (ver MarcaObserver)
                // y en la web, que muestra el logo llenando un círculo, un SVG
                // alargado se vería recortado.
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])
                ->maxSize(5120)
                ->directory('marcas')
                ->visibility('public')
                // Mismo recorte redondo que categorías: el logo se muestra
                // dentro de un círculo en la web.
                ->imageEditor()
                ->circleCropper()
                ->imageCropAspectRatio('1:1')
                ->imageEditorViewportWidth(400)
                ->imageEditorViewportHeight(400)
                ->helperText('Tocá el lápiz para mover y agrandar el logo dentro del círculo, igual que en WhatsApp.'),
            Forms\Components\Toggle::make('activo')
                ->default(true),
            Forms\Components\Toggle::make('iva')
                ->label('Aplicar IVA (21%)')
                ->helperText('Al prenderlo, todos los productos de esta marca suben el 21%. Al apagarlo vuelven al precio anterior. Prenderlo dos veces no lo suma dos veces.')
                // Mueve precios: sólo el admin.
                ->visible(fn () => auth()->user()?->isAdmin()),
            Forms\Components\TextInput::make('descuento_porcentaje')
                ->label('Descuento del proveedor')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->helperText('Lo usan todos los productos de esta marca que no tengan el suyo propio. Cargalo una vez acá y aparece en todos. Si mañana lo cambiás, cambian todos.')
                ->visible(fn () => auth()->user()?->isAdmin()),
            Forms\Components\TextInput::make('margen_porcentaje')
                ->label('Margen de ganancia')
                ->numeric()
                ->minValue(-99)
                ->maxValue(500)
                ->suffix('%')
                ->helperText('Lo usan todos los productos de esta marca que no tengan el suyo propio. Cargalo una vez acá y aparece en todos. Si mañana lo cambiás, cambian todos.')
                ->visible(fn () => auth()->user()?->isAdmin()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->circular()
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productos_count')
                    ->counts('productos')
                    ->label('Productos')
                    ->sortable(),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo'),
            ])
            // Sin esto Filament ofrece "Todos" en el desplegable, y la
            // elección queda guardada en la sesión: quien la toque deja la
            // pantalla colgada dos horas sin poder volver atrás.
            ->paginated([25, 50, 100])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Marca $record, Tables\Actions\DeleteAction $action) {
                        if ($record->productos()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('No se puede eliminar')
                                ->body('Esta marca todavía tiene productos asociados. Reasigná o eliminá esos productos primero.')
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                        ->before(function ($records, Tables\Actions\DeleteBulkAction $action) {
                            if ($records->contains(fn (Marca $r) => $r->productos()->exists())) {
                                Notification::make()
                                    ->danger()
                                    ->title('No se puede eliminar')
                                    ->body('Una o más marcas seleccionadas todavía tienen productos asociados.')
                                    ->send();
                                $action->cancel();
                            }
                        }),
                ]),
            ])
            ->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarcas::route('/'),
            'create' => Pages\CreateMarca::route('/create'),
            'edit' => Pages\EditMarca::route('/{record}/edit'),
        ];
    }
}
