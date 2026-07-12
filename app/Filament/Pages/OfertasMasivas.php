<?php

namespace App\Filament\Pages;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OfertasMasivas extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = 'Herramientas';

    protected static ?string $navigationLabel = 'Ofertas masivas';

    protected static ?string $title = 'Ofertas Masivas';

    protected static ?int $navigationSort = 41;

    protected static string $view = 'filament.pages.ofertas-masivas';

    public string $aplicar_a = 'marca';

    public ?string $marca_id = null;

    public ?string $categoria_id = null;

    public float $porcentaje = 10;

    public ?string $fecha_inicio = null;

    public ?string $fecha_fin = null;

    public array $preview = [];

    public bool $showPreview = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('aplicar_a')
                ->options([
                    'marca' => 'Por marca',
                    'categoria' => 'Por categoría',
                ])
                ->required()
                ->reactive(),
            Forms\Components\Select::make('marca_id')
                ->label('Marca')
                ->options(Marca::activos()->pluck('nombre', 'id'))
                ->searchable()
                ->visible(fn () => $this->aplicar_a === 'marca')
                ->required(fn () => $this->aplicar_a === 'marca'),
            Forms\Components\Select::make('categoria_id')
                ->label('Categoría')
                ->options(Categoria::activos()->pluck('nombre', 'id'))
                ->searchable()
                ->visible(fn () => $this->aplicar_a === 'categoria')
                ->required(fn () => $this->aplicar_a === 'categoria'),
            Forms\Components\TextInput::make('porcentaje')
                ->numeric()
                ->required()
                ->minValue(1)
                ->maxValue(90)
                ->suffix('%'),
            Forms\Components\DatePicker::make('fecha_inicio')
                ->label('Fecha inicio'),
            Forms\Components\DatePicker::make('fecha_fin')
                ->label('Fecha fin'),
        ]);
    }

    public function generarPreview(): void
    {
        $query = $this->buildQuery();
        if (! $query) {
            return;
        }

        $this->preview = $query->with('producto.marca')
            ->take(50)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'producto' => $p->producto->nombre,
                'marca' => $p->producto->marca->nombre,
                'unidad' => $p->unidad,
                'precio_actual' => (float) $p->precio,
                'precio_oferta' => round($p->precio * (1 - $this->porcentaje / 100), 2),
            ])
            ->toArray();

        $this->showPreview = true;
    }

    public function aplicarOfertas(): void
    {
        $query = $this->buildQuery();
        if (! $query) {
            return;
        }

        $count = $query->update([
            'oferta_porcentaje' => $this->porcentaje,
            'oferta_inicio' => $this->fecha_inicio,
            'oferta_fin' => $this->fecha_fin,
        ]);

        $this->showPreview = false;
        $this->preview = [];

        Notification::make()
            ->title("Oferta aplicada a {$count} presentaciones")
            ->success()
            ->send();
    }

    public function quitarOfertas(): void
    {
        $query = $this->buildQuery();
        if (! $query) {
            return;
        }

        $count = $query->update([
            'oferta_porcentaje' => null,
            'oferta_precio' => null,
            'oferta_inicio' => null,
            'oferta_fin' => null,
        ]);

        Notification::make()
            ->title("Oferta removida de {$count} presentaciones")
            ->success()
            ->send();
    }

    private function buildQuery()
    {
        $query = Presentacion::query();

        if ($this->aplicar_a === 'marca' && $this->marca_id) {
            $query->whereHas('producto', fn ($q) => $q->where('marca_id', $this->marca_id));
        } elseif ($this->aplicar_a === 'categoria' && $this->categoria_id) {
            $query->whereHas('producto', fn ($q) => $q->where('categoria_id', $this->categoria_id));
        } else {
            Notification::make()->title('Seleccioná una marca o categoría')->warning()->send();

            return null;
        }

        return $query;
    }
}
