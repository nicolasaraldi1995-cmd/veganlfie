<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Pago;
use App\Models\User;
use App\Services\CuentaClienteService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?int $navigationSort = 12;

    /**
     * Cuentas nuevas esperando alta: si no se ven en el menú, un cliente puede
     * quedar días sin poder comprar sin que nadie se entere.
     */
    public static function getNavigationBadge(): ?string
    {
        $pendientes = User::where('aprobado', false)->count();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('role')
                ->label('Rol')
                ->options([
                    'admin' => 'Administrador',
                    'operador' => 'Operador',
                    'cliente' => 'Cliente',
                ])
                ->required(),
            Forms\Components\TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create'),
            Forms\Components\Toggle::make('aprobado')
                ->label('Cuenta aprobada')
                ->helperText('Mientras esté apagado, el cliente no ve precios ni puede hacer pedidos. Las cuentas nuevas entran así hasta que las revises.'),
            Forms\Components\Toggle::make('recibe_frio_congelado')
                ->label('Puede recibir fríos/congelados')
                ->helperText('Si lo activás, a este cliente no le va a aparecer el aviso de "consultar disponibilidad de fríos/congelados" en el carrito.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'admin' => 'danger',
                        'operador' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('aprobado')
                    ->label('Alta')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-clock')
                    ->falseColor('warning')
                    ->tooltip(fn (User $record) => $record->aprobado ? 'Cuenta activa' : 'Esperando aprobación'),
                Tables\Columns\TextColumn::make('saldo')
                    ->label('Saldo')
                    ->state(fn (User $record) => app(CuentaClienteService::class)->saldoDe($record))
                    ->formatStateUsing(fn (?float $state) => $state === null ? '—' : '$'.number_format($state, 0, ',', '.'))
                    ->color(fn (?float $state) => $state !== null && $state > 0.009 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('sin_aprobar')
                    ->label('Esperando aprobación')
                    ->query(fn ($query) => $query->where('aprobado', false))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('¿Dar de alta esta cuenta?')
                    ->modalDescription('El cliente va a poder ver precios y hacer pedidos.')
                    ->visible(fn (User $record) => ! $record->aprobado)
                    ->action(function (User $record) {
                        $record->update(['aprobado' => true]);
                        Notification::make()->title("{$record->name} ya puede comprar")->success()->send();
                    }),
                Tables\Actions\Action::make('registrar_pago')
                    ->label('Registrar pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (User $record) => $record->role === 'cliente')
                    ->form([
                        Forms\Components\TextInput::make('monto')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->prefix('$')
                            ->autofocus(),
                        Forms\Components\Select::make('metodo')
                            ->options(Pago::METODOS)
                            ->required()
                            ->default('efectivo'),
                        Forms\Components\DatePicker::make('fecha')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('notas')
                            ->placeholder('Nota opcional'),
                    ])
                    ->action(function (User $record, array $data) {
                        Pago::create([
                            'user_id' => $record->id,
                            'monto' => $data['monto'],
                            'metodo' => $data['metodo'],
                            'fecha' => $data['fecha'],
                            'notas' => $data['notas'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Pago de $'.number_format($data['monto'], 0, ',', '.').' registrado para '.$record->name)
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
