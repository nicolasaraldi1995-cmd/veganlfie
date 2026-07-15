<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ProductRow from '@/Components/ProductRow.vue';
import ProductCard from '@/Components/ProductCard.vue';
import ImageModal from '@/Components/ImageModal.vue';
import ComboDetailModal from '@/Components/ComboDetailModal.vue';
import BannerSlider from '@/Components/BannerSlider.vue';
import WelcomeGuideModal from '@/Components/WelcomeGuideModal.vue';
import { Link, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
function addCombo(id) { router.post(route('cart.add-combo'), { combo_id: id }, { preserveScroll: true }); }

const props = defineProps({ banners: Array, pasillos: Array, combos: Array, masVendidos: Array, mostrarGuiaBienvenida: Boolean });

const modalImage = ref(null);
const comboSeleccionado = ref(null);
const mostrarGuia = ref(props.mostrarGuiaBienvenida);

function scrollTo(id) {
    document.getElementById('cat-' + id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <Head title="Inicio">
        <meta name="description" content="VEGANLIFE — Distribuidora de productos veganos en Pergamino. Más de 1500 productos: alimentos, bebidas, congelados, sin TACC y más." />
        <meta property="og:title" content="VEGANLIFE — Distribuidora Vegana" />
        <meta property="og:description" content="Productos veganos al por mayor y menor. Pedí online y recibí en tu negocio." />
        <meta property="og:type" content="website" />
    </Head>
    <PublicLayout>
        <WelcomeGuideModal v-if="mostrarGuia" @close="mostrarGuia = false" />
        <BannerSlider :banners="banners" />

        <div class="px-6 py-5">
            <!-- Info banner -->
            <div class="mb-6 bg-sky-500/5 border border-sky-500/15 rounded-xl px-5 py-3.5 flex items-start gap-3">
                <svg class="w-5 h-5 text-sky-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                <div>
                    <p class="text-[13px] text-text leading-relaxed">Trabajamos con <span class="font-medium text-sky-400">comisionistas</span> en distintas zonas. Los productos <span class="font-medium text-sky-400">fríos y congelados</span> pueden no estar disponibles en todas las localidades.</p>
                    <p class="text-[11px] text-text-muted mt-1">Consultá disponibilidad para tu zona por WhatsApp antes de confirmar tu pedido.</p>
                </div>
            </div>

            <!-- Más vendidos -->
            <div v-if="masVendidos.length" class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-1 h-6 rounded-full bg-amber-400"></div>
                    <h2 class="text-[15px] section-title text-text">Más vendidos</h2>
                    <span class="text-[11px] text-text-muted bg-surface-2 px-2 py-0.5 rounded-lg">{{ masVendidos.length }}</span>
                </div>
                <ProductRow :productos="masVendidos" @image-click="modalImage = $event" />
            </div>

            <!-- Category aisles -->
            <div v-for="pasillo in pasillos" :key="pasillo.id" :id="'cat-' + pasillo.id" class="mb-8 scroll-mt-20">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-1 h-6 rounded-full bg-accent"></div>
                        <h2 class="text-[15px] section-title text-text">{{ pasillo.nombre }}</h2>
                        <span class="text-[11px] text-text-muted bg-surface-2 px-2 py-0.5 rounded-lg">{{ pasillo.total }}</span>
                    </div>
                    <Link :href="route('productos.index', { vista: 'categorias', categoria: pasillo.id })"
                        class="text-[12px] font-semibold text-white bg-accent hover:bg-accent-bright px-4 py-1.5 rounded-lg transition-all active:scale-[0.97]">
                        Ver todos →
                    </Link>
                </div>
                <ProductRow :productos="pasillo.productos" @image-click="modalImage = $event" />
            </div>

            <!-- Combos -->
            <div v-if="combos.length" class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-1 h-6 rounded-full bg-accent"></div>
                        <h2 class="text-[15px] section-title text-text">Combos</h2>
                    </div>
                    <Link :href="route('combos.index')" class="text-[12px] text-accent hover:text-accent-bright transition">Ver todos →</Link>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="c in combos" :key="c.id" @click="comboSeleccionado = c" class="cursor-pointer bg-surface-2 rounded-2xl border border-border overflow-hidden hover:border-border-hover transition-all duration-300">
                        <div class="relative aspect-video bg-surface-3">
                            <img v-if="c.imagen_url" :src="c.imagen_url" loading="lazy" class="w-full h-full object-cover" />
                            <span class="absolute top-2.5 left-2.5 bg-purple-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-lg">COMBO</span>
                        </div>
                        <div class="p-5">
                            <h3 class="font-medium text-text">{{ c.nombre }}</h3>
                            <p v-if="c.descripcion" class="text-sm text-text-muted mt-1 line-clamp-2">{{ c.descripcion }}</p>
                            <div class="flex items-center justify-between mt-3">
                                <div>
                                <del v-if="c.descuento_porcentaje && c.precio_sin_descuento !== c.precio_final" class="text-[12px] text-text-muted">${{ Math.round(c.precio_sin_descuento).toLocaleString('es-AR') }}</del>
                                <span v-if="c.descuento_porcentaje" class="text-[11px] text-red-400 ml-1">-{{ c.descuento_porcentaje }}%</span>
                                <p class="text-lg font-semibold text-text">${{ Math.round(c.precio_final).toLocaleString('es-AR') }}</p>
                            </div>
                                <button @click.stop="addCombo(c.id)" class="bg-accent hover:bg-accent-bright text-white text-[12px] font-semibold px-4 py-2 rounded-lg transition-all active:scale-[0.98]">Agregar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ImageModal :src="modalImage" @close="modalImage = null" />
        <ComboDetailModal :combo="comboSeleccionado" @close="comboSeleccionado = null" />
    </PublicLayout>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
</style>
