<?php

namespace App\Filament\Pages;

use App\Models\Configuracion as ConfiguracionModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Configuracion extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Herramientas';

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $title = 'Configuración del sitio';

    protected static ?int $navigationSort = 43;

    protected static string $view = 'filament.pages.configuracion';

    public ?float $envio_gratis_desde = null;

    public bool $controlar_stock = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $actual = ConfiguracionModel::actual();
        $this->envio_gratis_desde = (float) $actual->envio_gratis_desde;
        $this->controlar_stock = (bool) $actual->controlar_stock;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Envío')
                ->description('Definí a partir de qué monto de compra el envío sale gratis.')
                ->schema([
                    Forms\Components\TextInput::make('envio_gratis_desde')
                        ->label('Envío gratis a partir de')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('$')
                        ->helperText('Se muestra en el carrito y en el checkout.'),
                ]),
            Forms\Components\Section::make('Stock')
                ->description('Definí si el stock cargado limita lo que se puede comprar.')
                ->schema([
                    Forms\Components\Toggle::make('controlar_stock')
                        ->label('Controlar stock')
                        ->helperText('Si lo apagás, se puede comprar cualquier producto en cualquier cantidad sin importar el stock cargado. El número de stock sigue existiendo y se sigue actualizando con cada pedido (podés reactivar el control cuando quieras), solo deja de frenar la compra.'),
                ]),
        ]);
    }

    public function guardar(): void
    {
        $this->validate();

        ConfiguracionModel::actual()->update([
            'envio_gratis_desde' => $this->envio_gratis_desde,
            'controlar_stock' => $this->controlar_stock,
        ]);

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }
}
