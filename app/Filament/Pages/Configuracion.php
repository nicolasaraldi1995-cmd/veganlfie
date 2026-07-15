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

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->envio_gratis_desde = (float) ConfiguracionModel::actual()->envio_gratis_desde;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('envio_gratis_desde')
                ->label('Envío gratis a partir de')
                ->numeric()
                ->required()
                ->minValue(0)
                ->prefix('$')
                ->helperText('Monto mínimo de compra para que el envío salga gratis. Se muestra en el carrito y en el checkout.'),
        ]);
    }

    public function guardar(): void
    {
        $this->validate();

        ConfiguracionModel::actual()->update([
            'envio_gratis_desde' => $this->envio_gratis_desde,
        ]);

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }
}
