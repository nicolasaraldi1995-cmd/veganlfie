<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import Pagination from '@/Components/Pagination.vue';
import ImageModal from '@/Components/ImageModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ modo: String, productos: Object, productosPorCategoria: Array, totalResultados: Number, items: Array, breadcrumb: Array, marcas: Array, categorias: Array, categoriaActual: Object, marcaActual: Object, filtros: Object });
const modalImage = ref(null);
const buscar = ref(props.filtros.buscar || '');
let deb = null;
watch(buscar, (v) => { clearTimeout(deb); deb = setTimeout(() => { if (v.length >= 2) router.get(route('productos.index'), { buscar: v }, { preserveState: true, replace: true }); else if (!v) router.get(route('productos.index'), {}, { preserveState: true, replace: true }); }, 400); });
</script>

<template>
    <Head title="Productos" />
    <PublicLayout>
        <div class="px-6 py-8">
            <nav v-if="breadcrumb?.length" class="flex items-center gap-1.5 text-[13px] text-text-muted mb-6">
                <Link :href="route('home')" class="hover:text-accent transition">Inicio</Link>
                <template v-for="(c, i) in breadcrumb" :key="i">
                    <span class="text-surface-4">/</span>
                    <Link v-if="c.url" :href="c.url" class="hover:text-accent transition">{{ c.label }}</Link>
                    <span v-else class="text-text">{{ c.label }}</span>
                </template>
            </nav>

            <div class="mb-6">
                <div class="relative max-w-md">
                    <input v-model="buscar" type="text" placeholder="Buscar productos, marcas, categorías..."
                        class="w-full pl-10 pr-4 py-3 bg-surface-2 border border-border rounded-xl text-[13px] focus:border-accent focus:ring-1 focus:ring-accent/20 placeholder:text-text-muted transition-all" />
                    <svg class="w-4 h-4 text-text-muted absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>

            <!-- Categorias grid -->
            <template v-if="modo === 'categorias'">
                <h1 class="text-xl font-semibold text-text mb-6">Categorías</h1>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Link v-for="cat in items" :key="cat.id" :href="route('productos.index', { vista: 'categorias', categoria: cat.id })" class="group">
                        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden hover:border-border-hover transition-all duration-300">
                            <div class="aspect-[4/3] bg-surface-3 relative">
                                <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500" />
                                <div v-else class="w-full h-full flex items-center justify-center"><span class="text-2xl font-bold text-surface-4">{{ cat.nombre.charAt(0) }}</span></div>
                                <span class="absolute bottom-2.5 right-2.5 bg-surface/80 backdrop-blur-sm text-text text-[10px] font-medium px-2 py-0.5 rounded-lg">{{ cat.productos_count }}</span>
                            </div>
                        </div>
                        <p class="text-[13px] font-medium text-text-secondary mt-2.5 text-center group-hover:text-text transition">{{ cat.nombre }}</p>
                    </Link>
                </div>
            </template>

            <!-- Marcas in category -->
            <template v-else-if="modo === 'marcas_en_categoria'">
                <h1 class="text-xl font-semibold text-text mb-1">{{ categoriaActual?.nombre }}</h1>
                <p class="text-[13px] text-text-muted mb-6">{{ items.length }} marcas</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Link v-for="m in items" :key="m.id" :href="route('productos.index', { vista: 'categorias', categoria: categoriaActual.id, marca: m.id })"
                        class="bg-surface-2 rounded-2xl border border-border p-6 flex flex-col items-center justify-center hover:border-border-hover hover:bg-surface-3 transition-all duration-300 min-h-[140px]">
                        <img v-if="m.logo_url" :src="m.logo_url" class="max-h-10 object-contain opacity-60 mb-3" />
                        <span v-else class="text-lg font-bold text-surface-4 mb-3">{{ m.nombre.charAt(0) }}</span>
                        <span class="text-[13px] text-text-secondary font-medium">{{ m.nombre }}</span>
                        <span class="text-[10px] text-text-muted mt-1">{{ m.productos_count }} prod.</span>
                    </Link>
                </div>
            </template>

            <!-- Marcas grid -->
            <template v-else-if="modo === 'marcas'">
                <h1 class="text-xl font-semibold text-text mb-6">Marcas</h1>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Link v-for="m in items" :key="m.id" :href="route('productos.index', { vista: 'marcas', marca: m.id })"
                        class="bg-surface-2 rounded-2xl border border-border p-6 flex flex-col items-center justify-center hover:border-border-hover hover:bg-surface-3 transition-all duration-300 min-h-[140px]">
                        <img v-if="m.logo_url" :src="m.logo_url" class="max-h-10 object-contain opacity-60 mb-3" />
                        <span v-else class="text-lg font-bold text-surface-4 mb-3">{{ m.nombre.charAt(0) }}</span>
                        <span class="text-[13px] text-text-secondary font-medium">{{ m.nombre }}</span>
                        <span class="text-[10px] text-text-muted mt-1">{{ m.productos_count }} prod.</span>
                    </Link>
                </div>
            </template>

            <!-- Categories in brand -->
            <template v-else-if="modo === 'categorias_en_marca'">
                <div class="flex items-center gap-4 mb-1">
                    <img v-if="marcaActual?.logo_url" :src="marcaActual.logo_url" class="h-10 object-contain opacity-60" />
                    <h1 class="text-xl font-semibold text-text">{{ marcaActual?.nombre }}</h1>
                </div>
                <p class="text-[13px] text-text-muted mb-6">{{ items.length }} categorías</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Link v-for="cat in items" :key="cat.id" :href="route('productos.index', { vista: 'marcas', marca: marcaActual.id, categoria: cat.id })" class="group">
                        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden hover:border-border-hover transition-all duration-300">
                            <div class="aspect-[4/3] bg-surface-3 relative">
                                <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500" />
                                <div v-else class="w-full h-full flex items-center justify-center"><span class="text-2xl font-bold text-surface-4">{{ cat.nombre.charAt(0) }}</span></div>
                                <span class="absolute bottom-2.5 right-2.5 bg-surface/80 backdrop-blur-sm text-text text-[10px] font-medium px-2 py-0.5 rounded-lg">{{ cat.productos_count }}</span>
                            </div>
                        </div>
                        <p class="text-[13px] font-medium text-text-secondary mt-2.5 text-center group-hover:text-text transition">{{ cat.nombre }}</p>
                    </Link>
                </div>
            </template>

            <!-- Search grouped -->
            <template v-else-if="modo === 'busqueda' && productosPorCategoria?.length">
                <p class="text-[13px] text-text-muted mb-6">{{ totalResultados }} resultados para "{{ filtros.buscar }}"</p>
                <div v-for="g in productosPorCategoria" :key="g.nombre" class="mb-12">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-0.5 h-6 rounded-full bg-accent"></div>
                        <h2 class="text-[15px] font-semibold text-text">{{ g.nombre }}</h2>
                        <span class="text-[11px] text-accent bg-accent/10 px-2 py-0.5 rounded-lg">{{ g.productos.length }}</span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <ProductCard v-for="p in g.productos" :key="p.id" :producto="p" @image-click="modalImage = $event" />
                    </div>
                </div>
            </template>

            <!-- Products flat -->
            <template v-else-if="modo === 'productos' && productos">
                <div v-if="productos.data.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <ProductCard v-for="p in productos.data" :key="p.id" :producto="p" @image-click="modalImage = $event" />
                </div>
                <div v-else class="text-center py-20 text-text-muted">Sin resultados.</div>
                <Pagination :links="productos.links" />
            </template>

            <div v-else class="text-center py-20 text-text-muted">Sin resultados.</div>
        </div>
        <ImageModal :src="modalImage" @close="modalImage = null" />
    </PublicLayout>
</template>
