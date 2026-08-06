<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoriaResource\Pages;
use App\Models\Categoria;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    // Sin esto el menú decía "Categorias", sin tilde: el nombre lo arma
    // Filament a partir del modelo, que no lleva acentos.
    protected static ?string $modelLabel = 'Categoría';

    protected static ?string $pluralModelLabel = 'Categorías';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 14;

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
            Forms\Components\FileUpload::make('imagen')
                ->image()
                ->acceptedFileTypes(ProductoResource::IMAGENES)
                ->maxSize(5120)
                ->directory('categorias')
                ->visibility('public')
                // Recorte redondo estilo WhatsApp: en la web la categoría se
                // muestra dentro de un círculo, así que se elige acá qué parte
                // de la foto queda adentro.
                ->imageEditor()
                ->circleCropper()
                ->imageCropAspectRatio('1:1')
                ->imageEditorViewportWidth(400)
                ->imageEditorViewportHeight(400)
                ->helperText('Tocá el lápiz para mover y agrandar la foto dentro del círculo, igual que en WhatsApp.'),
            Forms\Components\TextInput::make('orden')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('imagen')
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('orden')
                    ->sortable(),
                Tables\Columns\TextColumn::make('productos_count')
                    ->counts('productos')
                    ->label('Productos')
                    ->sortable(),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
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
                    ->before(function (Categoria $record, Tables\Actions\DeleteAction $action) {
                        if ($record->productos()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('No se puede eliminar')
                                ->body('Esta categoría todavía tiene productos asociados. Reasigná o eliminá esos productos primero.')
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
                            if ($records->contains(fn (Categoria $r) => $r->productos()->exists())) {
                                Notification::make()
                                    ->danger()
                                    ->title('No se puede eliminar')
                                    ->body('Una o más categorías seleccionadas todavía tienen productos asociados.')
                                    ->send();
                                $action->cancel();
                            }
                        }),
                ]),
            ])
            ->defaultSort('orden');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategorias::route('/'),
            'create' => Pages\CreateCategoria::route('/create'),
            'edit' => Pages\EditCategoria::route('/{record}/edit'),
        ];
    }
}
