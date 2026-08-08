<?php

namespace App\Filament\Pages;

use App\Models\Marca;
use App\Models\Presentacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ActualizarPrecios extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Herramientas';

    protected static ?string $navigationLabel = 'Actualizar precios';

    protected static ?string $title = 'Actualizar Precios por Marca';

    protected static ?int $navigationSort = 41;

    protected static string $view = 'filament.pages.actualizar-precios';

    public ?string $marca_id = null;

    public float $porcentaje = 10;

    public array $preview = [];

    public bool $showPreview = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('marca_id')
                ->label('Marca')
                ->options(Marca::orderBy('nombre')->pluck('nombre', 'id'))
                ->searchable()
                ->required()
                ->reactive(),
            Forms\Components\TextInput::make('porcentaje')
                ->label('Porcentaje de aumento')
                ->numeric()
                ->required()
                ->minValue(-99)
                ->maxValue(500)
                ->suffix('%')
                ->helperText('Positivo para subir, negativo para bajar. Ej: 15 sube un 15%, -10 baja un 10%.'),
        ]);
    }

    public function generarPreview(): void
    {
        if (! $this->marca_id) {
            Notification::make()->title('Seleccioná una marca')->warning()->send();

            return;
        }

        $this->validate();

        $this->preview = Presentacion::whereHas('producto', fn ($q) => $q->where('marca_id', $this->marca_id))
            ->with('producto')
            ->orderBy('producto_id')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'producto' => $p->producto->nombre,
                'unidad' => $p->unidad,
                'precio_actual' => (float) $p->precio,
                'precio_nuevo' => round($p->precio * (1 + $this->porcentaje / 100), 2),
            ])
            ->toArray();

        $this->showPreview = true;
    }

    public function aplicarAumento(): void
    {
        if (! $this->marca_id) {
            Notification::make()->title('Seleccioná una marca')->warning()->send();

            return;
        }

        $this->validate();

        $factor = 1 + $this->porcentaje / 100;

        // Una marca grande no entra en el tiempo por defecto de PHP: el
        // comentario del importador ya deja constancia de que un corte a mitad
        // de un guardado por fila pasó de verdad.
        set_time_limit(120);

        // Todo o nada. Sin la transacción, un corte a la mitad dejaba media
        // marca al +15% y el resto no; el admin no veía el aviso de éxito,
        // volvía a apretar "Aplicar" y las primeras recibían el aumento por
        // segunda vez (+32,25% acumulado), sin forma de distinguirlas después.
        // chunkById para no traer miles de modelos a memoria de una.
        $cuantas = 0;
        DB::transaction(function () use ($factor, &$cuantas) {
            Presentacion::whereHas('producto', fn ($q) => $q->where('marca_id', $this->marca_id))
                ->chunkById(200, function ($presentaciones) use ($factor, &$cuantas) {
                    foreach ($presentaciones as $p) {
                        $p->precio = round($p->precio * $factor, 2);
                        $p->saveQuietly();
                        $cuantas++;
                    }
                });
        });

        $marca = Marca::find($this->marca_id);

        $this->showPreview = false;
        $this->preview = [];

        $signo = $this->porcentaje >= 0 ? '+' : '';

        Notification::make()
            ->title("Precios actualizados: {$signo}{$this->porcentaje}% en {$marca->nombre} ({$cuantas} presentaciones)")
            ->success()
            ->send();
    }
}
