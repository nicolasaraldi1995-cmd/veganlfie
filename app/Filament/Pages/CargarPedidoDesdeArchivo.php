<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PedidoResource;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carga el archivo de pedido que arma el cliente desde la lista de precios que
 * se le manda por WhatsApp, y lo convierte en un pedido real. Evita tener que
 * tipear a mano lo que el cliente ya cargó.
 */
class CargarPedidoDesdeArchivo extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    // Distinto al del Importador: con el mismo icono los dos, en el menú se
    // confundía "subir un pedido" con "subir la lista de precios".
    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Pedido desde archivo';

    protected static ?string $title = 'Cargar pedido desde archivo';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.cargar-pedido-desde-archivo';

    public ?string $cliente_id = null;

    /**
     * El contenido del .json, leído en el navegador. Se manda el texto (unos
     * cientos de bytes) en vez de subir el archivo: la subida de archivos de
     * Livewire viene dando problemas en este proyecto y acá no hace falta.
     */
    public string $contenido = '';

    public array $vistaPrevia = [];

    public bool $mostrarPrevia = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isOperador());
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('cliente_id')
                ->label('¿De qué cliente es el pedido?')
                ->options(fn () => User::where('role', 'cliente')
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (User $u) => [
                        $u->id => $u->name.($u->negocio ? " ({$u->negocio})" : ''),
                    ]))
                ->searchable()
                ->required(),
        ]);
    }

    public function previsualizar(): void
    {
        $this->validate();

        try {
            $items = $this->leerArchivo();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        $this->vistaPrevia = $items;
        $this->mostrarPrevia = true;
    }

    public function confirmar(): void
    {
        $this->validate();

        try {
            $items = $this->leerArchivo();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        $cliente = User::findOrFail($this->cliente_id);

        try {
            $pedido = DB::transaction(function () use ($items, $cliente) {
                $pedido = Pedido::create([
                    'user_id' => $cliente->id,
                    'estado' => 'pending',
                    'total' => 0,
                    'datos_cliente' => [
                        'nombre' => $cliente->name,
                        'negocio' => $cliente->negocio,
                        'tipo_cliente' => $cliente->tipo_cliente,
                        'email' => $cliente->email,
                        'celular' => $cliente->celular,
                        'direccion' => $cliente->direccion,
                        'ciudad' => $cliente->ciudad,
                        'provincia' => $cliente->provincia,
                        'entrega' => 'envio',
                        'notas' => 'Cargado desde el archivo que mandó el cliente.',
                    ],
                ]);

                foreach ($items as $item) {
                    PedidoItem::create([
                        'pedido_id' => $pedido->id,
                        'presentacion_id' => $item['presentacion_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'subtotal' => $item['subtotal'],
                    ]);
                }

                $pedido->recalcularTotal();

                return $pedido;
            });
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        $this->reset(['cliente_id', 'contenido', 'vistaPrevia', 'mostrarPrevia']);

        Notification::make()
            ->title("Pedido #{$pedido->id} creado para {$cliente->name}")
            ->success()
            ->send();

        $this->redirect(PedidoResource::getUrl('view', ['record' => $pedido->id]));
    }

    /**
     * @return array<int, array{presentacion_id: int, cantidad: int, nombre: string, unidad: string, precio: float, subtotal: float}>
     */
    private function leerArchivo(): array
    {
        if (trim($this->contenido) === '') {
            throw ValidationException::withMessages(['contenido' => 'Elegí el archivo del pedido.']);
        }

        $datos = json_decode($this->contenido, true);

        if (! is_array($datos) || ! isset($datos['items']) || ! is_array($datos['items'])) {
            throw ValidationException::withMessages([
                'contenido' => 'El archivo no tiene el formato esperado. Tiene que ser el .json que genera la lista de precios.',
            ]);
        }

        // Los precios se toman de la base, no del archivo: el cliente pudo haber
        // armado el pedido con una lista vieja, y el que vale es el actual.
        // whereHas descarta las presentaciones cuyo producto fue dado de baja:
        // no se pueden pedir, y de paso deja garantizado que la relación existe.
        $presentaciones = Presentacion::with('producto')
            ->whereHas('producto')
            ->whereIn('id', collect($datos['items'])->pluck('id')->filter()->all())
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($datos['items'] as $linea) {
            $presentacion = $presentaciones->get($linea['id'] ?? null);
            $cantidad = (int) ($linea['cantidad'] ?? 0);

            if (! $presentacion || $cantidad < 1) {
                continue;
            }

            $precio = $presentacion->precio_final;

            $items[] = [
                'presentacion_id' => $presentacion->id,
                'cantidad' => $cantidad,
                'nombre' => $presentacion->producto->nombre,
                'unidad' => $presentacion->unidad,
                'precio' => $precio,
                'subtotal' => round($precio * $cantidad, 2),
            ];
        }

        if (! $items) {
            throw ValidationException::withMessages([
                'contenido' => 'El archivo no tiene productos que sigan disponibles.',
            ]);
        }

        return $items;
    }
}
