<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ producto: Object });
const emit = defineEmits(['imageClick']);
const page = usePage();

const selectedIndex = ref(0);
const cantidad = ref(1);
const showControls = ref(false);
const presentaciones = computed(() => props.producto.presentaciones || []);
const selected = computed(() => presentaciones.value[selectedIndex.value]);
const precioOriginal = computed(() => selected.value ? parseFloat(selected.value.precio) : 0);

const enOferta = computed(() => {
    if (!selected.value) return false;
    const p = selected.value;
    if (!p.oferta_porcentaje && !p.oferta_precio) return false;
    const now = new Date().toISOString().split('T')[0];
    if (p.oferta_inicio && p.oferta_inicio > now) return false;
    if (p.oferta_fin && p.oferta_fin < now) return false;
    return true;
});

const precioFinal = computed(() => {
    if (!selected.value) return 0;
    if (enOferta.value) {
        if (selected.value.oferta_precio) return parseFloat(selected.value.oferta_precio);
        if (selected.value.oferta_porcentaje) return Math.round(precioOriginal.value * (1 - selected.value.oferta_porcentaje / 100) * 100) / 100;
    }
    return precioOriginal.value;
});

const descuento = computed(() => {
    if (!enOferta.value || !selected.value) return 0;
    if (selected.value.oferta_porcentaje) return Math.round(selected.value.oferta_porcentaje);
    if (selected.value.oferta_precio) return Math.round((1 - parseFloat(selected.value.oferta_precio) / precioOriginal.value) * 100);
    return 0;
});

const stock = computed(() => selected.value?.stock ?? 0);
const sinStock = computed(() => stock.value <= 0);

const enCarrito = computed(() => {
    if (!selected.value) return false;
    return page.props.cartPresentacionIds?.includes(selected.value.id) ?? false;
});

const precioUnidad = computed(() => {
    if (!selected.value) return null;
    const unidad = selected.value.unidad?.toLowerCase() || '';
    const precio = precioFinal.value;
    const match = unidad.match(/^(\d+(?:[.,]\d+)?)\s*(g|gr|grs?|kg|kgs?|ml|cc|lt?|lts?|litros?)$/i);
    if (!match) return null;
    let val = parseFloat(match[1].replace(',', '.'));
    const unit = match[2].toLowerCase();
    if (['g', 'gr', 'grs', 'gr'].includes(unit)) return { precio: Math.round(precio / val * 1000), unidad: 'kg' };
    if (['kg', 'kgs'].includes(unit)) return { precio: Math.round(precio / val), unidad: 'kg' };
    if (['ml', 'cc'].includes(unit)) return { precio: Math.round(precio / val * 1000), unidad: 'lt' };
    if (['l', 'lt', 'lts', 'litros'].includes(unit)) return { precio: Math.round(precio / val), unidad: 'lt' };
    return null;
});

function selectPresentation(i) { selectedIndex.value = i; cantidad.value = 1; }
function addToCart() {
    if (!selected.value || sinStock.value) return;
    router.post(route('cart.add'), { presentacion_id: selected.value.id, cantidad: cantidad.value }, { preserveScroll: true });
    cantidad.value = 1;
    showControls.value = false;
}
const imageSrc = computed(() => {
    if (selected.value?.imagen) return `/storage/${selected.value.imagen}`;
    if (props.producto.imagen) return `/storage/${props.producto.imagen}`;
    return null;
});
</script>

<template>
    <div class="bg-surface-1 rounded-xl border overflow-hidden flex flex-col h-full transition-all duration-300"
        :class="enCarrito ? 'border-accent/40 shadow-sm shadow-accent/5' : 'border-border hover:border-border-hover hover:shadow-lg hover:shadow-black/8'">

        <!-- Image -->
        <div class="relative aspect-square bg-surface-2 overflow-hidden cursor-pointer shrink-0" @click="imageSrc && emit('imageClick', imageSrc)">
            <img v-if="imageSrc" :src="imageSrc" :alt="producto.nombre" loading="lazy" class="w-full h-full object-cover hover:scale-[1.03] transition-transform duration-500" />
            <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-8 h-8 text-surface-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <span v-if="enOferta && descuento > 0" class="absolute top-2.5 right-2.5 bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-lg">-{{ descuento }}%</span>
            <span v-if="enCarrito" class="absolute top-2.5 left-2.5 bg-accent text-white text-[9px] font-bold px-2 py-0.5 rounded-lg flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                En carrito
            </span>
        </div>

        <div class="p-4 flex-1 flex flex-col">
            <!-- Badges: contenedor siempre presente para que todas las cards arranquen el título a la misma altura -->
            <div class="flex gap-1.5 flex-wrap mb-2 min-h-[18px]">
                <span v-if="producto.sin_tacc" class="text-[8px] font-bold uppercase tracking-wider text-accent bg-accent/10 px-1.5 py-0.5 rounded">Sin TACC</span>
                <span v-if="producto.frio" class="text-[8px] font-bold uppercase tracking-wider text-sky-500 bg-sky-500/10 px-1.5 py-0.5 rounded">Frío</span>
                <span v-if="producto.congelado" class="text-[8px] font-bold uppercase tracking-wider text-blue-500 bg-blue-500/10 px-1.5 py-0.5 rounded">Congelado</span>
                <span v-if="producto.nuevo" class="text-[8px] font-bold uppercase tracking-wider text-amber-500 bg-amber-500/10 px-1.5 py-0.5 rounded">Nuevo</span>
            </div>

            <h3 class="section-title text-text text-[13px] leading-snug line-clamp-2 min-h-[2.5rem]">{{ producto.nombre }}</h3>
            <p class="brand-label text-text-muted mt-1 truncate">{{ producto.marca?.nombre }}</p>

            <!-- Pills: alto fijo y recorte de overflow para que productos con muchas o pocas presentaciones ocupen el mismo espacio -->
            <div class="mt-3 min-h-[26px] overflow-hidden">
                <div v-if="presentaciones.length > 1" class="flex flex-wrap gap-1.5">
                    <button v-for="(p, i) in presentaciones" :key="p.id" @click="selectPresentation(i)"
                        class="px-2.5 py-[5px] text-[11px] font-medium rounded-lg transition-all duration-200"
                        :class="i === selectedIndex ? 'bg-accent text-white' : 'bg-surface-3 text-text-secondary hover:bg-surface-4 hover:text-text'">
                        {{ p.unidad }}
                    </button>
                </div>
                <p v-else-if="presentaciones.length === 1" class="text-[11px] text-text-muted leading-[26px]">{{ presentaciones[0].unidad }}</p>
            </div>

            <!-- Price -->
            <div class="mt-auto pt-3">
                <div v-if="selected" class="flex items-baseline gap-2">
                    <span class="text-xl price-display truncate" :class="enOferta ? 'text-red-500' : 'text-text'">${{ precioFinal.toLocaleString('es-AR') }}</span>
                    <del v-if="enOferta" class="text-[11px] text-text-muted shrink-0">${{ precioOriginal.toLocaleString('es-AR') }}</del>
                </div>
                <p v-if="precioUnidad" class="text-[10px] text-text-muted mt-0.5">${{ precioUnidad.precio.toLocaleString('es-AR') }}/{{ precioUnidad.unidad }}</p>
                <p class="text-[10px] mt-0.5" :class="sinStock ? 'text-red-400' : 'text-text-muted'">{{ sinStock ? 'Sin stock' : `Stock: ${stock}` }}</p>
            </div>

            <!-- Add to cart: mutable button -->
            <div class="mt-3">
                <Transition name="fade" mode="out-in">
                    <div v-if="showControls && !sinStock" key="controls" class="flex items-center gap-2">
                        <div class="inline-flex items-center bg-surface-3 rounded-lg shrink-0">
                            <button @click="cantidad = Math.max(1, cantidad - 1)"
                                class="w-8 h-8 flex items-center justify-center text-text-muted hover:text-text text-sm transition">−</button>
                            <input type="number" :value="cantidad" @change="e => { let v = parseInt(e.target.value) || 1; cantidad = Math.max(1, Math.min(v, stock)); e.target.value = cantidad; }"
                                min="1" :max="stock"
                                class="w-10 h-8 text-center text-[12px] font-semibold text-text bg-transparent border-0 p-0 focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" />
                            <button @click="cantidad = Math.min(cantidad + 1, stock)"
                                class="w-8 h-8 flex items-center justify-center text-text-muted hover:text-text text-sm transition">+</button>
                        </div>
                        <button @click="addToCart"
                            class="flex-1 text-[13px] font-bold py-2.5 rounded-lg bg-accent text-white shadow-sm shadow-accent/25 hover:bg-accent-bright hover:shadow-md hover:shadow-accent/30 active:scale-[0.98] transition-all">
                            Agregar
                        </button>
                    </div>
                    <button v-else key="add" @click="sinStock ? null : (showControls = true)" :disabled="sinStock"
                        class="w-full flex items-center justify-center gap-1.5 text-[13px] font-bold py-3 rounded-lg transition-all duration-200"
                        :class="sinStock ? 'bg-surface-3 text-text-muted cursor-not-allowed' : 'bg-accent text-white shadow-sm shadow-accent/25 hover:bg-accent-bright hover:shadow-md hover:shadow-accent/30 active:scale-[0.98]'">
                        <svg v-if="!sinStock" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        {{ sinStock ? 'Sin stock' : (enCarrito ? 'Agregar más' : 'Agregar al carrito') }}
                    </button>
                </Transition>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
