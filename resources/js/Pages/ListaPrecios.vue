<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ categorias: Array, marcas: Array });

const buscar = ref('');
const marcaFiltro = ref('');
const expandidas = ref({});

// Planilla informal: cantidades sueltas para sacar un total rápido, sin tocar
// el carrito ni generar un pedido. La clave es producto+unidad, que es lo único
// que identifica a una presentación en esta vista.
const cantidades = ref({});

function claveDe(producto, pres) {
    return `${producto.id}|${pres.unidad}`;
}

// Índice plano para no recorrer todo el catálogo en cada tecleo.
const presentacionPorClave = computed(() => {
    const mapa = {};
    props.categorias.forEach(cat => cat.productos.forEach(p => p.presentaciones.forEach(pres => {
        mapa[claveDe(p, pres)] = {
            producto: p.nombre,
            marca: p.marca,
            unidad: pres.unidad,
            precio_final: pres.precio_final,
        };
    })));
    return mapa;
});

const totalPlanilla = computed(() =>
    Object.entries(cantidades.value).reduce((suma, [clave, cant]) => {
        const n = parseFloat(cant);
        if (!n || n <= 0) return suma;
        const pres = presentacionPorClave.value[clave];
        return pres ? suma + pres.precio_final * n : suma;
    }, 0)
);

const lineasCargadas = computed(() =>
    Object.entries(cantidades.value)
        .filter(([, c]) => parseFloat(c) > 0)
        .map(([clave, c]) => ({
            clave,
            cantidad: parseFloat(c),
            ...presentacionPorClave.value[clave],
        }))
        .filter(l => l.precio_final !== undefined)
);

function limpiarPlanilla() {
    cantidades.value = {};
}

function copiarPlanilla() {
    const lineas = lineasCargadas.value.map(l =>
        `${l.cantidad} x ${l.producto} (${l.unidad}) — $${Math.round(l.precio_final * l.cantidad).toLocaleString('es-AR')}`
    );
    lineas.push(`TOTAL: $${Math.round(totalPlanilla.value).toLocaleString('es-AR')}`);
    navigator.clipboard?.writeText(lineas.join('\n'));
}

function toggleCat(id) {
    expandidas.value[id] = !expandidas.value[id];
}

function expandirTodas() {
    props.categorias.forEach(c => expandidas.value[c.id] = true);
}

function colapsarTodas() {
    expandidas.value = {};
}

const categoriasFiltradas = computed(() => {
    const term = buscar.value.toLowerCase().trim();
    const marca = marcaFiltro.value;

    return props.categorias
        .map(cat => {
            let productos = cat.productos;

            if (marca) {
                productos = productos.filter(p => p.marca === marca);
            }
            if (term) {
                productos = productos.filter(p =>
                    p.nombre.toLowerCase().includes(term) ||
                    p.marca.toLowerCase().includes(term)
                );
            }

            return { ...cat, productos };
        })
        .filter(cat => cat.productos.length > 0);
});

const totalProductos = computed(() => categoriasFiltradas.value.reduce((s, c) => s + c.productos.length, 0));
const totalPresentaciones = computed(() => categoriasFiltradas.value.reduce((s, c) => s + c.productos.reduce((s2, p) => s2 + p.presentaciones.length, 0), 0));

// Auto-expand when searching
import { watch } from 'vue';
watch([buscar, marcaFiltro], () => {
    if (buscar.value || marcaFiltro.value) {
        categoriasFiltradas.value.forEach(c => expandidas.value[c.id] = true);
    }
});
</script>

<template>
    <Head title="Lista de precios">
        <meta name="description" content="Lista de precios actualizada de VEGANLIFE. Consultá productos, marcas y presentaciones." />
    </Head>
    <PublicLayout>
        <div class="px-6 py-8 max-w-6xl mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-text">Lista de precios</h1>
                    <p class="text-[12px] text-text-muted mt-1">{{ totalProductos }} productos · {{ totalPresentaciones }} presentaciones</p>
                </div>
                <div class="flex gap-2 shrink-0">
                    <a :href="route('lista-precios.html')" class="inline-flex items-center gap-2 bg-accent hover:bg-accent-bright text-white text-[13px] font-medium px-5 py-2.5 rounded-xl transition-all"
                        title="Archivo liviano con buscador, para mandar por WhatsApp">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v13.5m0 0l4.5-4.5M12 16.5L7.5 12M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5"/></svg>
                        Descargar para WhatsApp
                    </a>
                    <a :href="route('lista-precios.planilla')" class="inline-flex items-center gap-2 bg-surface-2 hover:bg-surface-3 text-text-secondary text-[13px] font-medium px-4 py-2.5 rounded-xl transition-all border border-border"
                        title="Planilla para abrir en Excel, editar precios y volver a subirla desde el Importador">
                        Planilla
                    </a>
                    <a :href="route('lista-precios.pdf')" class="inline-flex items-center gap-2 bg-surface-2 hover:bg-surface-3 text-text-secondary text-[13px] font-medium px-4 py-2.5 rounded-xl transition-all border border-border"
                        title="Versión para imprimir">
                        PDF
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-3 mb-6">
                <div class="relative flex-1">
                    <input v-model="buscar" type="text" placeholder="Buscar producto o marca..."
                        class="w-full pl-9 pr-3 py-2.5 text-[13px] bg-surface-1 border border-border rounded-xl focus:border-accent focus:ring-1 focus:ring-accent/20 placeholder:text-text-muted transition" />
                    <svg class="w-4 h-4 text-text-muted absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <select v-model="marcaFiltro" class="bg-surface-1 border border-border rounded-xl px-4 py-2.5 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition">
                    <option value="">Todas las marcas</option>
                    <option v-for="m in marcas" :key="m" :value="m">{{ m }}</option>
                </select>
                <div class="flex gap-2">
                    <button @click="expandirTodas" class="text-[12px] text-accent hover:text-accent-bright transition px-3 py-2 rounded-xl bg-surface-1 border border-border">Abrir todas</button>
                    <button @click="colapsarTodas" class="text-[12px] text-text-muted hover:text-text transition px-3 py-2 rounded-xl bg-surface-1 border border-border">Cerrar todas</button>
                </div>
            </div>

            <!-- Categories -->
            <div v-if="categoriasFiltradas.length" class="space-y-3">
                <div v-for="cat in categoriasFiltradas" :key="cat.id" class="bg-surface-1 rounded-2xl border border-border overflow-hidden">
                    <!-- Category header -->
                    <button @click="toggleCat(cat.id)" class="w-full px-5 py-3.5 flex items-center justify-between hover:bg-surface-2/50 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-1 h-5 rounded-full bg-accent"></div>
                            <span class="text-[14px] font-semibold text-text">{{ cat.nombre }}</span>
                            <span class="text-[11px] text-text-muted bg-surface-2 px-2 py-0.5 rounded-lg">{{ cat.productos.length }}</span>
                        </div>
                        <svg class="w-4 h-4 text-text-muted transition-transform" :class="expandidas[cat.id] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <!-- Products table -->
                    <!-- Se puede arrastrar de costado: en el celular la tabla mide
                         623px dentro de 325, y la tarjeta padre tiene overflow-hidden,
                         así que se recortaba sin barra. Quedaban invisibles e
                         inalcanzables Oferta, Stock, Cant. e Importe: la planilla
                         entera, que es para lo que se usa esta pantalla. -->
                    <div v-if="expandidas[cat.id]" class="border-t border-border overflow-x-auto">
                        <table class="w-full min-w-[640px] text-[12px]">
                            <thead>
                                <tr class="bg-surface-2/50">
                                    <th class="px-5 py-2 text-left font-medium text-text-muted">Producto</th>
                                    <th class="px-3 py-2 text-left font-medium text-text-muted">Marca</th>
                                    <th class="px-3 py-2 text-left font-medium text-text-muted">Presentación</th>
                                    <th class="px-3 py-2 text-right font-medium text-text-muted">Precio</th>
                                    <th class="px-3 py-2 text-right font-medium text-text-muted">Oferta</th>
                                    <th class="px-5 py-2 text-right font-medium text-text-muted">Stock</th>
                                    <th class="px-3 py-2 text-center font-medium text-accent">Cant.</th>
                                    <th class="px-3 py-2 text-right font-medium text-accent">Importe</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <template v-for="producto in cat.productos" :key="producto.id">
                                    <tr v-for="(pres, i) in producto.presentaciones" :key="pres.unidad" class="hover:bg-surface-2/30 transition-colors">
                                        <td v-if="i === 0" :rowspan="producto.presentaciones.length" class="px-5 py-2 align-top">
                                            <span class="font-medium text-text">{{ producto.nombre }}</span>
                                            <span v-if="producto.sin_tacc" class="ml-1 text-[8px] font-bold text-accent bg-accent/10 px-1 py-0.5 rounded">TACC</span>
                                            <span v-if="producto.frio" class="ml-1 text-[8px] font-bold text-sky-400 bg-sky-400/10 px-1 py-0.5 rounded">FRÍO</span>
                                            <span v-if="producto.congelado" class="ml-1 text-[8px] font-bold text-blue-400 bg-blue-400/10 px-1 py-0.5 rounded">CONG</span>
                                        </td>
                                        <td v-if="i === 0" :rowspan="producto.presentaciones.length" class="px-3 py-2 text-text-muted align-top">{{ producto.marca }}</td>
                                        <td class="px-3 py-2">{{ pres.unidad }}</td>
                                        <td class="px-3 py-2 text-right font-medium text-text">
                                            <span :class="pres.en_oferta ? 'line-through text-text-muted font-normal text-[11px]' : ''">${{ pres.precio.toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <span v-if="pres.en_oferta" class="font-semibold text-red-400">${{ pres.precio_final.toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</span>
                                            <span v-else class="text-text-muted">—</span>
                                        </td>
                                        <td class="px-5 py-2 text-right">
                                            <span v-if="pres.stock > 0" class="text-text-muted">{{ pres.stock }}</span>
                                            <span v-else class="text-red-400 text-[10px]">Sin stock</span>
                                        </td>
                                        <!-- Planilla: se escribe la cantidad y suma sola, sin tocar el carrito -->
                                        <td class="px-3 py-1.5 text-center">
                                            <input v-model="cantidades[claveDe(producto, pres)]" type="number" min="0" inputmode="numeric" placeholder="—"
                                                class="w-16 text-center text-[12px] py-1 px-1 rounded-lg border transition"
                                                :class="cantidades[claveDe(producto, pres)] > 0 ? 'border-accent bg-accent/10 text-text font-semibold' : 'border-border bg-surface-1 text-text-muted'" />
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold tabular-nums"
                                            :class="cantidades[claveDe(producto, pres)] > 0 ? 'text-accent' : 'text-text-muted/40'">
                                            {{ cantidades[claveDe(producto, pres)] > 0
                                                ? '$' + Math.round(pres.precio_final * cantidades[claveDe(producto, pres)]).toLocaleString('es-AR')
                                                : '—' }}
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-20 text-text-muted">No se encontraron productos.</div>

            <!-- Espacio para que la barra no tape la última fila -->
            <div v-if="lineasCargadas.length" class="h-24"></div>
        </div>

        <!-- Planilla informal: aparece sola al cargar la primera cantidad -->
        <Transition name="slide-up">
            <div v-if="lineasCargadas.length" class="fixed bottom-0 left-0 right-0 z-40 bg-surface-1 border-t border-border shadow-[0_-4px_20px_-8px_rgba(0,0,0,0.25)]">
                <div class="max-w-6xl mx-auto px-6 py-3.5 flex items-center gap-4 flex-wrap">
                    <div class="flex items-baseline gap-2">
                        <span class="text-[11px] text-text-muted uppercase tracking-wider font-semibold">Total</span>
                        <span class="text-2xl font-bold text-text tabular-nums">${{ Math.round(totalPlanilla).toLocaleString('es-AR') }}</span>
                    </div>
                    <span class="text-[12px] text-text-muted">{{ lineasCargadas.length }} {{ lineasCargadas.length === 1 ? 'producto' : 'productos' }}</span>

                    <div class="flex gap-2 ml-auto">
                        <button @click="copiarPlanilla" class="text-[12px] font-semibold px-4 py-2 rounded-xl bg-surface-2 hover:bg-surface-3 text-text-secondary border border-border transition">
                            Copiar
                        </button>
                        <button @click="limpiarPlanilla" class="text-[12px] font-semibold px-4 py-2 rounded-xl bg-surface-2 hover:bg-surface-3 text-text-muted border border-border transition">
                            Borrar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </PublicLayout>
</template>

<style scoped>
.slide-up-enter-active, .slide-up-leave-active { transition: transform .25s ease, opacity .25s ease; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); opacity: 0; }
@media (prefers-reduced-motion: reduce) {
    .slide-up-enter-active, .slide-up-leave-active { transition: none; }
}
</style>
