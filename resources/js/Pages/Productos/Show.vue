<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import ImageModal from '@/Components/ImageModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ producto: Object, relacionados: Array });
const selectedIndex = ref(0);
const cantidad = ref(1);
const modalImage = ref(null);
const presentaciones = computed(() => props.producto.presentaciones || []);
const selected = computed(() => presentaciones.value[selectedIndex.value]);
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
        if (selected.value.oferta_porcentaje) return Math.round(parseFloat(selected.value.precio) * (1 - selected.value.oferta_porcentaje / 100) * 100) / 100;
    }
    return parseFloat(selected.value.precio);
});
const imageSrc = computed(() => {
    if (selected.value?.imagen) return `/storage/${selected.value.imagen}`;
    if (props.producto.imagen) return `/storage/${props.producto.imagen}`;
    return null;
});
const stock = computed(() => selected.value?.stock ?? 0);
const sinStock = computed(() => stock.value <= 0);
const jsonLd = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: props.producto.nombre,
    ...(props.producto.marca ? { brand: { '@type': 'Brand', name: props.producto.marca.nombre } } : {}),
    ...(imageSrc.value ? { image: [window.location.origin + imageSrc.value] } : {}),
    ...(selected.value ? {
        offers: {
            '@type': 'Offer',
            priceCurrency: 'ARS',
            price: precioFinal.value,
            availability: sinStock.value ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
            url: window.location.href,
        },
    } : {}),
}));
function addToCart() {
    if (!selected.value || sinStock.value) return;
    router.post(route('cart.add'), { presentacion_id: selected.value.id, cantidad: cantidad.value }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="producto.nombre">
        <meta :content="`${producto.nombre} — ${producto.marca?.nombre}. Comprá online en VEGANLIFE.`" name="description" />
        <meta :content="producto.nombre" property="og:title" />
        <meta :content="`${producto.nombre} de ${producto.marca?.nombre}. Pedí online.`" property="og:description" />
        <meta v-if="producto.imagen" :content="`/storage/${producto.imagen}`" property="og:image" />
        <meta content="product" property="og:type" />
        <script type="application/ld+json" v-html="jsonLd"></script>
    </Head>
    <PublicLayout>
        <div class="px-6 py-8">
            <nav class="text-[13px] text-text-muted mb-6">
                <Link :href="route('home')" class="hover:text-accent transition">Inicio</Link>
                <span class="mx-2 text-surface-4">/</span>
                <Link :href="route('productos.index')" class="hover:text-accent transition">Productos</Link>
                <span class="mx-2 text-surface-4">/</span>
                <span class="text-text">{{ producto.nombre }}</span>
            </nav>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="aspect-[3/2] bg-surface-2 rounded-xl border border-border overflow-hidden cursor-pointer"
                     @click="imageSrc && (modalImage = imageSrc)">
                    <img v-if="imageSrc" :src="imageSrc" :alt="producto.nombre" class="w-full h-full object-cover hover:scale-[1.03] transition-transform duration-500" />
                    <div v-else class="w-full h-full flex items-center justify-center"><svg class="w-16 h-16 text-surface-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                </div>

                <div>
                    <div class="flex gap-2 mb-3">
                        <span v-if="producto.sin_tacc" class="text-[9px] font-semibold uppercase tracking-wider text-accent bg-accent/10 px-2 py-1 rounded-lg">Sin TACC</span>
                        <span v-if="producto.frio" class="text-[9px] font-semibold uppercase tracking-wider text-sky-400 bg-sky-400/10 px-2 py-1 rounded-lg">Frío</span>
                        <span v-if="producto.congelado" class="text-[9px] font-semibold uppercase tracking-wider text-blue-400 bg-blue-400/10 px-2 py-1 rounded-lg">Congelado</span>
                        <span v-if="producto.nuevo" class="text-[9px] font-semibold uppercase tracking-wider text-amber-400 bg-amber-400/10 px-2 py-1 rounded-lg">Nuevo</span>
                    </div>
                    <h1 class="text-2xl section-title text-text">{{ producto.nombre }}</h1>
                    <p class="brand-label text-text-muted mt-2">
                        <Link v-if="producto.marca" :href="route('marcas.show', producto.marca.slug)" class="hover:text-accent transition">{{ producto.marca.nombre }}</Link>
                        <span v-if="producto.marca && producto.categoria" class="mx-1 text-surface-4">·</span>
                        <Link v-if="producto.categoria" :href="route('categorias.show', producto.categoria.slug)" class="hover:text-accent transition">{{ producto.categoria.nombre }}</Link>
                    </p>
                    <p v-if="producto.descripcion" class="text-text-secondary mt-4 leading-relaxed text-[15px]">{{ producto.descripcion }}</p>

                    <div class="mt-6">
                        <p class="text-[12px] font-semibold text-text-muted uppercase tracking-wider mb-2">Presentación</p>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="(p, i) in presentaciones" :key="p.id" @click="selectedIndex = i; cantidad = 1"
                                class="px-4 py-2.5 rounded-xl text-[13px] font-medium border transition-all"
                                :class="i === selectedIndex ? 'border-accent bg-accent/10 text-accent' : 'border-border text-text-secondary hover:border-border-hover hover:text-text'">
                                {{ p.unidad }}
                            </button>
                        </div>
                    </div>

                    <div v-if="selected" class="mt-6">
                        <div class="flex items-baseline gap-3">
                            <del v-if="enOferta" class="text-lg text-text-muted">${{ parseFloat(selected.precio).toLocaleString('es-AR') }}</del>
                            <span class="text-3xl price-display" :class="enOferta ? 'text-red-500' : 'text-text'">${{ precioFinal.toLocaleString('es-AR') }}</span>
                        </div>
                    </div>

                    <div v-if="selected" class="mt-3">
                        <p class="text-[11px] mb-3" :class="sinStock ? 'text-red-400' : 'text-text-muted'">{{ sinStock ? 'Sin stock' : `Stock: ${stock}` }}</p>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center bg-surface-2 rounded-xl border border-border">
                                <button @click="cantidad = Math.max(1, cantidad - 1)" :disabled="sinStock || cantidad <= 1" class="px-4 py-3 text-text-muted hover:text-text transition disabled:opacity-30">−</button>
                                <input type="number" :value="cantidad" @change="e => { let v = parseInt(e.target.value) || 1; cantidad = Math.max(1, Math.min(v, stock)); e.target.value = cantidad; }"
                                    :disabled="sinStock" min="1" :max="stock"
                                    class="w-14 py-3 text-center font-semibold text-text bg-transparent border-0 p-0 focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" />
                                <button @click="cantidad = Math.min(cantidad + 1, stock)" :disabled="sinStock || cantidad >= stock" class="px-4 py-3 text-text-muted hover:text-text transition disabled:opacity-30">+</button>
                            </div>
                            <button @click="addToCart" :disabled="sinStock"
                                class="flex-1 font-semibold py-3 rounded-xl transition-all active:scale-[0.98]"
                                :class="sinStock ? 'bg-surface-3 text-text-muted cursor-not-allowed' : 'bg-accent hover:bg-accent-bright text-white'">
                                {{ sinStock ? 'Sin stock' : 'Agregar al carrito' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="relacionados.length" class="mt-16">
                <h2 class="text-lg font-semibold text-text mb-6">Productos relacionados</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <ProductCard v-for="p in relacionados" :key="p.id" :producto="p" @image-click="modalImage = $event" />
                </div>
            </div>
        </div>
        <ImageModal :src="modalImage" @close="modalImage = null" />
    </PublicLayout>
</template>
