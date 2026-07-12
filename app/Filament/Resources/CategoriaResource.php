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

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 22;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')
                ->required()
                ->maxLength(255),
            Forms\Components\FileUpload::make('imagen')
                ->image()
                ->maxSize(2048)
                ->directory('categorias')
                ->visibility('public'),
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
                Tables\Columns\ImageColumn::make('imagen'),
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
