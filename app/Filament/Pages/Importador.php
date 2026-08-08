<?php

namespace App\Filament\Pages;

use App\Services\ProductImportService;
use App\Services\SincronizarCatalogo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\WithFileUploads;

class Importador extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Herramientas';

    protected static ?string $navigationLabel = 'Importador';

    /**
     * Del dueño, como Actualizar precios y Ofertas masivas. Subir un archivo
     * acá reescribe el precio de todo el catálogo de una, y de paso puede dar
     * de baja lo que no figure: eso no es carga de pedidos, es manejo de
     * precios. Para pasar a pedido lo que mandó un cliente está "Pedido desde
     * archivo", que el operador sí abre.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected static ?string $title = 'Importar Productos';

    protected static ?int $navigationSort = 40;

    protected static string $view = 'filament.pages.importador';

    /**
     * Filament entrega el archivo como lista, no como texto. Declararlo
     * ?string hacía que asignarle el estado del formulario reventara con
     * "Cannot assign array to property": por eso este importador nunca llegaba
     * a leer el archivo. Se normaliza en rutaGuardada().
     *
     * @var array<int|string, mixed>|string|null
     */
    public array|string|null $archivo = null;

    public int $header_row = 5;

    public array $headers = [];

    public array $columnMap = [
        'nombre' => '',
        'marca' => '',
        'categoria' => '',
        'unidad' => '',
        'precio' => '',
        'stock' => '',
        'sin_tacc' => '',
        'congelado' => '',
        'nuevo' => '',
    ];

    public bool $actualizar_existentes = true;

    public string $step = 'upload';

    public array $previewData = [];

    public array $importResult = [];

    /**
     * Qué haría la sincronización con este archivo: se muestra junto a la
     * previsualización para poder decidir antes de importar.
     *
     * @var array<string, mixed>
     */
    public array $resumenSync = [];

    /** Arranca apagado: dar de baja productos no se hace sin pedirlo. */
    public bool $sincronizar = false;

    /** @var array<string, int> */
    public array $syncResult = [];

    /**
     * Sin esto el formulario nunca queda inicializado y Filament valida un
     * estado vacío: al tocar "Siguiente" avisaba que el archivo es obligatorio
     * aunque estuviera elegido. En los tests no se notaba porque fillForm()
     * inicializa el formulario por su cuenta.
     */
    public function mount(): void
    {
        $this->getForm('form')?->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('archivo')
                ->label('Archivo Excel o CSV')
                // La lista que exporta el sistema viejo es en realidad una tabla
                // HTML con extensión .xls: el navegador la anuncia como
                // text/html y el formulario la rechazaba. La librería de Excel
                // la lee igual, así que se aceptan también esos tipos. El
                // archivo se valida de verdad al leerlo, no acá.
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'text/csv',
                    'text/html',
                    'text/plain',
                    'application/octet-stream',
                ])
                // Al disco privado, NO al del panel. El del panel se symlinkea
                // a public/storage, así que la lista del proveedor con los
                // costos se bajaba entera desde /storage/imports/ sin cuenta:
                // Apache la sirve antes de llegar a PHP y el filtro de
                // MediaController ni se entera. "visibility private" no salva
                // nada en un disco local, no saca el archivo de esa carpeta.
                ->disk(self::DISCO)
                ->directory('imports')
                ->visibility('private')
                ->maxSize(10240)
                ->required()
                ->visible(fn () => $this->step === 'upload'),
            Forms\Components\TextInput::make('header_row')
                ->label('Fila de encabezados')
                ->numeric()
                ->default(5)
                ->helperText('Número de fila donde están los nombres de columna (tu Excel usa fila 5)')
                ->visible(fn () => $this->step === 'upload'),
        ]);
    }

    /**
     * storage/app/private: fuera del árbol que publica el servidor web, a
     * diferencia del disco del panel. La lista del proveedor trae los costos y
     * los márgenes, así que no puede quedar donde Apache la alcance.
     */
    private const DISCO = 'local';

    /**
     * Cuántas listas se guardan. Cada una pesa un mega y medio y no se vuelven
     * a mirar; se dejan unas pocas por si hay que revisar qué se importó.
     */
    private const CUANTAS_SE_GUARDAN = 5;

    /**
     * Borra las listas viejas. Sin esto se juntaban para siempre: una por cada
     * vez que se actualizan los precios, sin que nadie las saque nunca.
     */
    private function borrarLasViejas(): void
    {
        $disco = Storage::disk(self::DISCO);
        $archivos = collect($disco->files('imports'))
            ->sortByDesc(fn (string $archivo) => $disco->lastModified($archivo))
            ->slice(self::CUANTAS_SE_GUARDAN);

        foreach ($archivos as $archivo) {
            $disco->delete($archivo);
        }
    }

    private function rutaGuardada(): ?string
    {
        $valor = $this->archivo;

        if (is_array($valor)) {
            $valor = reset($valor) ?: null;
        }

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        // $archivo es una propiedad pública de Livewire: viaja al navegador y
        // vuelve, así que con $wire.set se le puede poner cualquier cosa. Sin
        // este filtro, "../../../.env" salía de la carpeta de imports y el
        // importador leía y mostraba cualquier archivo del servidor (.env con
        // las credenciales, la APP_KEY). Mismo criterio que MediaController:
        // dentro de su carpeta y sin "..".
        if (str_contains($valor, '..') || ! str_starts_with($valor, 'imports/')) {
            return null;
        }

        return $valor;
    }

    /**
     * Devuelve una ruta de archivo que la librería de Excel pueda abrir. Si el
     * disco no es local (un bucket), se baja a un temporal y se lee de ahí.
     */
    private function rutaLocal(): string
    {
        $guardada = (string) $this->rutaGuardada();
        $disco = Storage::disk(self::DISCO);

        if ($disco->getAdapter() instanceof LocalFilesystemAdapter) {
            return $disco->path($guardada);
        }

        $extension = pathinfo($guardada, PATHINFO_EXTENSION) ?: 'xlsx';
        $temporal = tempnam(sys_get_temp_dir(), 'importador_').'.'.$extension;

        file_put_contents($temporal, $disco->get($guardada));

        return $temporal;
    }

    /**
     * Muestra el error de forma que se pueda entender y lo deja en el registro
     * del servidor. Antes, cuando algo fallaba fuera de un try, el usuario veía
     * un "500" pelado que no dice nada de qué salió mal.
     */
    private function avisarDelError(string $titulo, \Throwable $e): void
    {
        report($e);

        Notification::make()
            ->title($titulo)
            ->body($e->getMessage().' ('.class_basename($e).' en '.basename($e->getFile()).':'.$e->getLine().')')
            ->danger()
            ->persistent()
            ->send();
    }

    public function loadHeaders(): void
    {
        try {
            // getState() es lo que hace que el archivo subido se guarde de verdad
            // y devuelva su ruta. Sin esto, la propiedad todavía tiene el archivo
            // temporal de Livewire y no hay ninguna ruta que abrir.
            // Va adentro del try: guardar el archivo en el disco puede fallar, y
            // si esa falla se escapa, el usuario ve un "500" pelado en vez de
            // enterarse de qué pasó.
            $this->archivo = $this->getForm('form')?->getState()['archivo'] ?? $this->archivo;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->avisarDelError('No se pudo guardar el archivo', $e);

            return;
        }

        if (! $this->rutaGuardada()) {
            Notification::make()->title('Subí un archivo primero')->danger()->send();

            return;
        }

        try {
            $path = $this->rutaLocal();
            $service = new ProductImportService;
            $this->headers = $service->getHeaders($path, $this->header_row);

            $this->autoMapColumns();
            $this->step = 'map';
        } catch (\Throwable $e) {
            $this->avisarDelError('Error al leer el archivo', $e);
        }
    }

    public function generatePreview(): void
    {
        if (empty($this->columnMap['nombre']) || empty($this->columnMap['marca'])) {
            Notification::make()->title('Mapeá al menos Nombre y Marca')->warning()->send();

            return;
        }

        try {
            $path = $this->rutaLocal();
            $service = new ProductImportService;
            $this->previewData = $service->preview($path, $this->columnMap, $this->header_row);

            // Además de lo que se va a importar, se calcula lo que el archivo
            // deja afuera: el importador nunca da de baja nada, así que sin
            // esto un producto que el proveedor sacó de la lista queda
            // publicado para siempre.
            $this->resumenSync = $this->resumirSync(app(SincronizarCatalogo::class)->analizar($path, $this->header_row, $this->columnMap));

            $this->step = 'preview';
        } catch (\Throwable $e) {
            $this->avisarDelError('Error al generar la previsualización', $e);
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function resumirSync(array $plan): array
    {
        return [
            'cambiosDeMarca' => count($plan['cambiosDeMarca']),
            'cambiosDeNombre' => count($plan['cambiosDeNombre']),
            'bajas' => count($plan['bajas']),
            'ejemplosMarca' => array_slice($plan['cambiosDeMarca'], 0, 8),
            'ejemplosBaja' => array_slice($plan['bajas'], 0, 8),
            // Cuando esto es true, "Importar todo" va a frenar la sincronización.
            'peligroso' => $plan['peligroso'] ?? false,
            'totalActivos' => $plan['totalActivos'] ?? 0,
        ];
    }

    public function runImport(): void
    {
        try {
            $path = $this->rutaLocal();

            // Un catálogo grande no entra en el tiempo ni la memoria por defecto
            // de PHP, y la sincronización recorre cada producto contra cada
            // nombre de la lista, que es caro.
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            // La sincronización y la importación van juntas, en una sola
            // transacción. La sincronización mueve los productos a la marca que
            // les corresponde para que la importación de precios los encuentre;
            // si un corte las separaba, el catálogo quedaba reorganizado según
            // la lista nueva pero con los precios viejos, y los productos dados
            // de baja no volvían. Ahora o pasa todo o no pasa nada.
            DB::transaction(function () use ($path) {
                if ($this->sincronizar) {
                    $sincronizador = app(SincronizarCatalogo::class);
                    // Si el plan da de baja casi todo, aplicar() lanza y no toca
                    // nada: el catch de abajo muestra el motivo.
                    $this->syncResult = $sincronizador->aplicar($sincronizador->analizar($path, $this->header_row, $this->columnMap));
                }

                $service = new ProductImportService;
                $this->importResult = $service->import($path, $this->columnMap, $this->header_row, [
                    'actualizar_existentes' => $this->actualizar_existentes,
                ]);

                // Un error general de la importación (todo revertido de su lado)
                // tiene que revertir también la sincronización, o el catálogo
                // queda movido con los precios sin tocar. Los errores por grupo
                // (una fila mala) no cuentan: son esperados y no arrastran al resto.
                foreach ($this->importResult['errores'] as $error) {
                    if (str_starts_with($error, 'Error general')) {
                        throw new \RuntimeException($error);
                    }
                }
            });

            $this->step = 'result';
            $this->borrarLasViejas();

            $total = $this->importResult['productos_creados'] + $this->importResult['productos_actualizados'];
            $errores = $this->importResult['errores'] ?? [];

            // Con errores no se muestra un verde: el aviso rojo evita que el
            // dueño crea que actualizó los precios cuando en realidad falló y no
            // se cambió nada. Los detalles quedan en la pantalla de resultado.
            if ($errores !== []) {
                Notification::make()
                    ->title($total > 0
                        ? "Importación con problemas: {$total} procesados, ".count($errores).' con error'
                        : 'La importación falló y no se cambió nada')
                    ->body('Revisá el detalle abajo.')
                    ->danger()
                    ->persistent()
                    ->send();
            } else {
                Notification::make()
                    ->title("Importación completada: {$total} productos procesados")
                    ->success()
                    ->send();
            }
        } catch (\Throwable $e) {
            $this->avisarDelError('Error en la importación', $e);
        }
    }

    public function reset_form(): void
    {
        $this->archivo = null;
        $this->headers = [];
        $this->columnMap = [
            'nombre' => '', 'marca' => '', 'categoria' => '', 'unidad' => '',
            'precio' => '', 'stock' => '', 'sin_tacc' => '', 'congelado' => '', 'nuevo' => '',
        ];
        $this->previewData = [];
        $this->importResult = [];
        $this->resumenSync = [];
        $this->syncResult = [];
        $this->sincronizar = false;
        $this->step = 'upload';
    }

    private function autoMapColumns(): void
    {
        $aliases = [
            'nombre' => ['nombre', 'producto', 'name', 'descripcion'],
            'marca' => ['marca', 'brand'],
            'categoria' => ['categoria', 'categoría', 'category', 'rubro'],
            'unidad' => ['unidad', 'presentacion', 'presentación', 'unit', 'medida'],
            'precio' => ['precio', 'price', 'valor'],
            // Sin "cantidad": es demasiado ambiguo. Una planilla que trae una
            // columna "Cantidad" (la que se pide, no la que hay en depósito) la
            // mandaba a stock sola y pisaba el inventario. El Excel que exporta
            // la app ya llama a esa columna "Cant. a pedir" justamente por esto.
            'stock' => ['stock', 'qty'],
            'sin_tacc' => ['sin_tacc', 'sin tacc', 'tacc', 'gluten free'],
            'congelado' => ['congelado', 'frozen', 'freezado'],
            'nuevo' => ['nuevo', 'new'],
        ];

        foreach ($aliases as $field => $possibleNames) {
            foreach ($this->headers as $header) {
                if (in_array(mb_strtolower($header), $possibleNames)) {
                    $this->columnMap[$field] = $header;
                    break;
                }
            }
        }
    }
}
