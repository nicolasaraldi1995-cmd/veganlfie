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

    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')
                ->required()
                ->maxLength(255),
            Forms\Components\FileUpload::make('logo')
                ->image()
                ->maxSize(2048)
                ->directory('marcas')
                ->visibility('public'),
            Forms\Components\Toggle::make('activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->circular(),
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
