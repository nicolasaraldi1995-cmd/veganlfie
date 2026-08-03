<script setup>
import { computed } from 'vue';

const props = defineProps({ envioGratisDesde: Number, totalProductos: Number });

const plata = (n) => '$' + new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 }).format(n);
const miles = (n) => new Intl.NumberFormat('es-AR').format(n);

// Redondeado para abajo a la centena: "+1.500 productos" envejece mejor que un
// número exacto que cambia cada vez que se carga algo.
const productos = computed(() => Math.floor((props.totalProductos || 0) / 100) * 100);

const items = computed(() => [
    {
        titulo: 'Envío gratis',
        detalle: `Desde ${plata(props.envioGratisDesde)}`,
        icono: 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1',
    },
    {
        titulo: '100% vegano',
        detalle: 'Sin nada de origen animal',
        icono: 'M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10ZM2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12',
    },
    {
        titulo: `+${miles(productos.value)} productos`,
        detalle: 'Por mayor y por menor',
        icono: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    },
    {
        titulo: 'Pergamino y zona',
        detalle: 'Entrega a coordinar',
        icono: 'M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
    },
]);
</script>

<template>
    <!-- Franja que separa el banner del contenido. Las líneas de arriba y de
         abajo son el corte; los datos son los que pregunta todo el que entra
         por primera vez. -->
    <div class="border-y border-border">
        <!-- gap-px sobre el color del borde: dibuja las divisiones solas, sin
             importar en cuántas filas caiga la grilla. -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-border">
            <div v-for="d in items" :key="d.titulo"
                class="bg-surface-1 flex items-start gap-3 px-4 py-4 sm:px-5">
                <span class="w-9 h-9 rounded-xl bg-accent-muted text-accent-dim flex items-center justify-center shrink-0">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="d.icono" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-[13px] sm:text-sm font-semibold text-text leading-tight">{{ d.titulo }}</p>
                    <p class="text-[12px] sm:text-[13px] text-text-muted leading-snug mt-1">{{ d.detalle }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
