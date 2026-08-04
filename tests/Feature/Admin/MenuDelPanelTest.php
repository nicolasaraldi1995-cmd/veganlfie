<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El menú del admin se ordena con un número por pantalla. Cuando dos comparten
 * el mismo número el orden queda al azar, y cuando a una le falta el grupo
 * aparece suelta arriba de todo: así es como el panel se fue desparramando.
 */
class MenuDelPanelTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<class-string> */
    private function pantallas(): array
    {
        $clases = [];

        foreach (['Resources', 'Pages'] as $carpeta) {
            foreach (glob(app_path("Filament/{$carpeta}/*.php")) ?: [] as $archivo) {
                $clases[] = 'App\\Filament\\'.$carpeta.'\\'.basename($archivo, '.php');
            }
        }

        return $clases;
    }

    private function estatico(string $clase, string $propiedad): mixed
    {
        $propiedades = (new \ReflectionClass($clase))->getStaticProperties();

        return $propiedades[$propiedad] ?? null;
    }

    public function test_cada_pantalla_esta_en_un_grupo(): void
    {
        foreach ($this->pantallas() as $clase) {
            $this->assertNotNull(
                $this->estatico($clase, 'navigationGroup'),
                class_basename($clase).' quedaría suelta arriba del menú, sin grupo.'
            );
        }
    }

    public function test_no_hay_dos_pantallas_con_el_mismo_orden_en_un_grupo(): void
    {
        $vistos = [];

        foreach ($this->pantallas() as $clase) {
            $clave = $this->estatico($clase, 'navigationGroup').'|'.$this->estatico($clase, 'navigationSort');

            $this->assertArrayNotHasKey(
                $clave,
                $vistos,
                class_basename($clase).' y '.($vistos[$clave] ?? '').' comparten grupo y número de orden: '
                .'el menú las va a mostrar en cualquier orden.'
            );

            $vistos[$clave] = class_basename($clase);
        }
    }

    public function test_los_grupos_salen_en_el_orden_pensado(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();

        $grupos = array_values(array_filter(array_map(
            fn ($grupo) => $grupo->getLabel(),
            Filament::getNavigation()
        )));

        $this->assertSame(
            ['Ventas', 'Catálogo', 'Promociones', 'Finanzas', 'Herramientas'],
            $grupos
        );
    }
}
