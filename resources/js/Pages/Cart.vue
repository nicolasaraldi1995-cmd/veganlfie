<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import ImageModal from '@/Components/ImageModal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ items: Array, total: Number, recomendados: Array, pedidoAnterior: Object });
const page = usePage();
const modalImage = ref(null);
const mostrarComparacion = ref(false);

const FREE = 600000;
const progress = computed(() => Math.min((props.total / FREE) * 100, 100));
const tieneFrioOCongelado = computed(() => props.items.some(i => i.frio || i.congelado));
const mostrarAvisoFrio = computed(() => tieneFrioOCongelado.value && !page.props.auth.user?.recibe_frio_congelado);

const grupos = computed(() => {
    const porCategoria = {};
    for (const item of props.items) {
        const cat = item.categoria || 'Otros';
        (porCategoria[cat] ??= []).push(item);
    }
    return Object.keys(porCategoria).sort().map(nombre => ({
        nombre,
        items: porCategoria[nombre],
        subtotal: porCategoria[nombre].reduce((s, i) => s + i.subtotal, 0),
    }));
});

const productosFaltantes = computed(() => {
    if (!props.pedidoAnterior) return [];
    const idsEnCarrito = new Set(props.items.map(i => i.producto_id));
    const vistos = new Set();
    return props.pedidoAnterior.items.filter(it => {
        if (idsEnCarrito.has(it.producto_id) || vistos.has(it.producto_id)) return false;
        vistos.add(it.producto_id);
        return true;
    });
});

function updateQty(id, q) { router.patch(route('cart.update'), { presentacion_id: id, cantidad: q }, { preserveScroll: true }); }
function remove(id) { router.delete(route('cart.remove'), { data: { presentacion_id: id }, preserveScroll: true }); }
function quitarFrioCongelado() { router.delete(route('cart.remove-frio-congelado'), { preserveScroll: true }); }
function agregarFaltante(id) { router.post(route('cart.add'), { presentacion_id: id, cantidad: 1 }, { preserveScroll: true }); }
</script>
<template>
    <Head title="Carrito" />
    <PublicLayout>
        <div class="max-w-3xl mx-auto px-6 py-8">
            <div class="flex items-baseline justify-between mb-6">
                <h1 class="text-xl font-semibold text-text">Tu carrito</h1>
                <span v-if="items.length" class="text-[13px] text-text-muted">{{ items.length }} producto{{ items.length === 1 ? '' : 's' }}</span>
            </div>
            <div v-if="items.length">
                <div class="bg-surface-1 rounded-2xl border border-border p-5 mb-6">
                    <div class="flex justify-between text-[11px] mb-2">
                        <span v-if="total < FREE" class="text-accent">${{ (FREE - total).toLocaleString('es-AR') }} para envío gratis</span>
                        <span v-else class="text-accent">Envío gratis</span>
                    </div>
                    <div class="w-full bg-surface-3 rounded-full h-1"><div class="h-1 rounded-full bg-accent transition-all duration-700" :style="{ width: progress + '%' }"></div></div>
                </div>

                <div v-if="mostrarAvisoFrio" class="mb-6 bg-sky-500/5 border border-sky-500/15 rounded-xl px-5 py-3 flex items-start gap-3">
                    <svg class="w-5 h-5 text-sky-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                    <div class="flex-1">
                        <p class="text-[13px] text-text leading-relaxed">❄ Tu carrito tiene productos <span class="font-medium text-sky-400">fríos o congelados</span>. Consultá disponibilidad para tu zona antes de confirmar.</p>
                        <button @click="quitarFrioCongelado" class="mt-2 text-[12px] font-medium text-sky-400 hover:text-sky-300 underline transition">Quitar fríos/congelados del carrito</button>
                    </div>
                </div>

                <div v-if="pedidoAnterior" class="mb-6">
                    <button @click="mostrarComparacion = !mostrarComparacion" class="text-[13px] font-medium text-accent hover:text-accent-bright transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7h4v4M8 17H4v-4M15 4l-6 6m-1 4l6 6M20 4l-6 6-6-6"/></svg>
                        {{ mostrarComparacion ? 'Ocultar comparación' : 'Comparar con tu pedido anterior' }}
                    </button>

                    <div v-if="mostrarComparacion" class="mt-3 bg-surface-1 rounded-2xl border border-border p-5">
                        <p v-if="productosFaltantes.length" class="text-[12px] text-text-muted mb-3">
                            Estos productos estaban en tu pedido #{{ pedidoAnterior.id }} ({{ pedidoAnterior.fecha }}) y no están en el carrito actual:
                        </p>
                        <p v-else class="text-[13px] text-text-secondary">Ya tenés en el carrito todo lo que pediste la última vez. 🎉</p>

                        <div v-if="productosFaltantes.length" class="space-y-2">
                            <div v-for="it in productosFaltantes" :key="it.producto_id" class="flex items-center gap-3 px-3 py-2 rounded-xl bg-surface-2">
                                <div class="w-10 h-10 rounded-lg bg-surface-3 overflow-hidden shrink-0"><img v-if="it.imagen" :src="it.imagen" :alt="it.nombre" class="w-full h-full object-cover" /></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[12.5px] font-medium text-text truncate">{{ it.nombre }}</p>
                                    <p class="text-[11px] text-text-muted">{{ it.marca }} · {{ it.unidad }}</p>
                                </div>
                                <button @click="agregarFaltante(it.presentacion_id)" class="text-[11.5px] font-semibold text-white bg-accent hover:bg-accent-bright px-3 py-1.5 rounded-lg transition-all shrink-0">Agregar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-for="grupo in grupos" :key="grupo.nombre" class="mb-6">
                    <div class="flex items-center justify-between mb-2 px-1">
                        <h2 class="text-[12px] font-semibold text-text-secondary uppercase tracking-wide">{{ grupo.nombre }}</h2>
                        <span class="text-[12px] text-text-muted">${{ grupo.subtotal.toLocaleString('es-AR') }}</span>
                    </div>
                    <div class="bg-surface-1 rounded-2xl border border-border divide-y divide-border">
                        <div v-for="item in grupo.items" :key="item.presentacion_id" class="p-4 flex items-center gap-4">
                            <div class="w-14 h-14 rounded-lg bg-surface-2 overflow-hidden shrink-0">
                                <img v-if="item.imagen" :src="item.imagen" :alt="item.nombre" class="w-full h-full object-cover" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-medium text-text truncate">{{ item.nombre }}</p>
                                <p class="text-[11px] text-text-muted">{{ item.marca }} · {{ item.unidad }}</p>
                                <p class="text-[12px] text-text-secondary mt-0.5">${{ item.precio.toLocaleString('es-AR') }}</p>
                            </div>
                            <div class="flex items-center bg-surface-3 rounded-lg shrink-0">
                                <button @click="updateQty(item.presentacion_id, item.cantidad - 1)" class="w-8 h-8 flex items-center justify-center text-text-muted hover:text-text text-sm transition">−</button>
                                <span class="w-6 h-8 flex items-center justify-center text-[12px] font-semibold text-text">{{ item.cantidad }}</span>
                                <button @click="updateQty(item.presentacion_id, item.cantidad + 1)" class="w-8 h-8 flex items-center justify-center text-text-muted hover:text-text text-sm transition">+</button>
                            </div>
                            <p class="text-[13px] font-semibold text-text w-20 text-right shrink-0">${{ item.subtotal.toLocaleString('es-AR') }}</p>
                            <button @click="remove(item.presentacion_id)" class="text-text-muted hover:text-red-400 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                    </div>
                </div>

                <div class="mt-2 bg-surface-1 rounded-2xl border border-border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-text-secondary">Total</span>
                        <span class="text-xl font-semibold text-text">${{ total.toLocaleString('es-AR') }}</span>
                    </div>
                    <div class="flex gap-3">
                        <Link :href="route('productos.index')" class="flex-1 text-center border border-border text-text-secondary py-3 rounded-xl hover:bg-surface-2 hover:text-text transition text-[13px] font-medium">Seguir comprando</Link>
                        <Link :href="route('checkout.index')" class="flex-1 text-center bg-accent hover:bg-accent-bright text-white py-3 rounded-xl transition text-[13px] font-medium">Finalizar compra</Link>
                    </div>
                </div>

                <div v-if="recomendados.length" class="mt-8">
                    <div class="flex items-center gap-3 mb-5"><div class="w-0.5 h-5 rounded-full bg-accent"></div><h2 class="text-[15px] font-semibold text-text">Te puede interesar</h2></div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3"><ProductCard v-for="p in recomendados" :key="p.id" :producto="p" @image-click="modalImage=$event" /></div>
                </div>
            </div>
            <div v-else class="text-center py-20">
                <p class="text-text-muted mb-4">Carrito vacío</p>
                <Link :href="route('productos.index')" class="text-accent hover:text-accent-bright text-sm transition">Explorar productos</Link>
            </div>
        </div>
        <ImageModal :src="modalImage" @close="modalImage = null" />
    </PublicLayout>
</template>
