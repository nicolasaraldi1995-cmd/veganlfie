<?php

namespace App\Filament\Pages;

use App\Services\ProductImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

class Importador extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Herramientas';

    protected static ?string $navigationLabel = 'Importador';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isOperador();
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
     * Devuelve una ruta de archivo que la librería de Excel pueda abrir.
     *
     * El formulario guarda el archivo en el disco que use el panel, que en
     * producción es un bucket: ahí no existe ninguna ruta local. Antes esto
     * pedía la ruta del disco "local" a secas, así que el importador fallaba
     * en producción. Se baja a un temporal y se lee de ahí.
     */
    private function rutaGuardada(): ?string
    {
        $valor = $this->archivo;

        if (is_array($valor)) {
            $valor = reset($valor) ?: null;
        }

        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    private function rutaLocal(): string
    {
        $guardada = (string) $this->rutaGuardada();
        $disco = Storage::disk(config('filament.default_filesystem_disk'));

        if ($disco->getAdapter() instanceof \League\Flysystem\Local\LocalFilesystemAdapter) {
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
            $this->step = 'preview';
        } catch (\Throwable $e) {
            $this->avisarDelError('Error al generar la previsualización', $e);
        }
    }

    public function runImport(): void
    {
        try {
            $path = $this->rutaLocal();
            $service = new ProductImportService;
            $this->importResult = $service->import($path, $this->columnMap, $this->header_row, [
                'actualizar_existentes' => $this->actualizar_existentes,
            ]);
            $this->step = 'result';

            $total = $this->importResult['productos_creados'] + $this->importResult['productos_actualizados'];
            Notification::make()
                ->title("Importación completada: {$total} productos procesados")
                ->success()
                ->send();
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
            'stock' => ['stock', 'cantidad', 'qty'],
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
