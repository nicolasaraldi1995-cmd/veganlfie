<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use App\Models\Categoria;
use App\Models\Marca;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 26;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('imagen')
                ->image()
                ->required()
                // 2 MB no alcanzaba para una imagen de banner en buena calidad.
                ->maxSize(8192)
                ->directory('banners')
                ->visibility('public')
                ->imagePreviewHeight('200')
                // El servidor solo la aliviana (ver BannerObserver); no recorta
                // nada, porque recortar le comía la parte de arriba y la de
                // abajo a las piezas más altas que la tira.
                ->imageEditor()
                ->imageEditorViewportWidth(800)
                ->imageEditorViewportHeight(320)
                ->helperText('Subí la imagen que tengas, de la medida que sea: entra entera, sin recortarse. Si querés que llene la tira justo, hacela de 1600 x 640 px.')
                ->columnSpanFull(),
            // Sin selectores de ajuste ni de posición: la imagen del inicio no
            // se recorta nunca, así que no hay nada que elegir. Mientras
            // existieron, alcanzaba con dejar uno en "recortar" para que el
            // diseño apareciera cortado en la página. Las columnas siguen en la
            // base por si hiciera falta volver atrás.
            Forms\Components\Select::make('destino_tipo')
                ->options([
                    'seccion' => 'Sección',
                    'marca' => 'Marca',
                    'categoria' => 'Categoría',
                    'url' => 'URL externa',
                ])
                ->default('url')
                ->required()
                ->reactive(),
            Forms\Components\Select::make('destino_valor')
                ->label('Destino')
                ->options(function (Forms\Get $get) {
                    return match ($get('destino_tipo')) {
                        'marca' => Marca::activos()->pluck('nombre', 'id')->toArray(),
                        'categoria' => Categoria::activos()->pluck('nombre', 'id')->toArray(),
                        'seccion' => [
                            'categorias' => 'Categorías',
                            'marcas' => 'Marcas',
                        ],
                        default => [],
                    };
                })
                ->searchable()
                ->visible(fn (Forms\Get $get) => in_array($get('destino_tipo'), ['marca', 'categoria', 'seccion'])),
            Forms\Components\TextInput::make('destino_valor')
                ->label('URL')
                ->placeholder('https://...')
                ->visible(fn (Forms\Get $get) => $get('destino_tipo') === 'url'),
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
                    ->height(60),
                Tables\Columns\TextColumn::make('destino_tipo')
                    ->badge()
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('destino_valor')
                    ->label('Destino')
                    ->limit(30),
                Tables\Columns\TextColumn::make('orden')
                    ->sortable(),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('orden')
            ->reorderable('orden');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
