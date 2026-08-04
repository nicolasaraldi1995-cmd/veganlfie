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
                <!-- Círculos grandes: la foto se recorta al centro, así entra cuadrada o no -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-x-4 gap-y-7">
                    <Link v-for="cat in items" :key="cat.id" :href="route('productos.index', { vista: 'categorias', categoria: cat.id })"
                        class="group flex flex-col items-center">
                        <div class="relative">
                            <div class="w-32 h-32 sm:w-36 sm:h-36 rounded-full overflow-hidden bg-surface-3 ring-1 ring-border group-hover:ring-accent/50 shadow-sm group-hover:shadow-md transition-all duration-300">
                                <img v-if="cat.imagen_url" :src="cat.imagen_url" :alt="cat.nombre"
                                    class="w-full h-full object-cover group-hover:scale-[1.06] transition-transform duration-500" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <span class="text-4xl font-bold text-surface-4">{{ cat.nombre.charAt(0) }}</span>
                                </div>
                            </div>
                            <span class="absolute bottom-1 right-1 bg-surface-1/95 backdrop-blur-sm text-text-secondary text-[11px] font-semibold px-2 py-0.5 rounded-full shadow-sm ring-1 ring-border">{{ cat.productos_count }}</span>
                        </div>
                        <p class="text-[15px] font-semibold text-text mt-3 text-center leading-snug group-hover:text-accent transition">{{ cat.nombre }}</p>
                    </Link>
                </div>
            </template>

            <!-- Marcas in category -->
            <template v-else-if="modo === 'marcas_en_categoria'">
                <h1 class="text-xl font-semibold text-text mb-1">{{ categoriaActual?.nombre }}</h1>
                <p class="text-[13px] text-text-muted mb-6">{{ items.length }} marcas</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-x-4 gap-y-7">
                    <Link v-for="m in items" :key="m.id" :href="route('productos.index', { vista: 'categorias', categoria: categoriaActual.id, marca: m.id })"
                        class="group flex flex-col items-center">
                        <div class="relative">
                            <!-- Sin margen y con object-cover: el archivo ya viene
                                 cuadrado desde el servidor (ver MarcaObserver), así
                                 que el logo llena el círculo tal como se recortó.
                                 El margen que había antes lo hacía verse chico,
                                 con un anillo blanco alrededor. -->
                            <div class="w-32 h-32 sm:w-36 sm:h-36 rounded-full overflow-hidden bg-surface-1 ring-1 ring-border group-hover:ring-accent/50 shadow-sm group-hover:shadow-md transition-all duration-300">
                                <img v-if="m.logo_url" :src="m.logo_url" :alt="m.nombre"
                                    class="w-full h-full object-cover group-hover:scale-[1.06] transition-transform duration-500" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <span class="text-4xl font-bold text-surface-4">{{ m.nombre.charAt(0) }}</span>
                                </div>
                            </div>
                            <span class="absolute bottom-1 right-1 bg-surface-1/95 backdrop-blur-sm text-text-secondary text-[11px] font-semibold px-2 py-0.5 rounded-full shadow-sm ring-1 ring-border">{{ m.productos_count }}</span>
                        </div>
                        <p class="text-[15px] font-semibold text-text mt-3 text-center leading-snug group-hover:text-accent transition">{{ m.nombre }}</p>
                    </Link>
                </div>
            </template>

            <!-- Marcas grid -->
            <template v-else-if="modo === 'marcas'">
                <h1 class="text-xl font-semibold text-text mb-6">Marcas</h1>
                <!-- Mismo círculo que categorías, pero el logo entra entero
                     (object-contain): recortarlo se comería parte de la marca -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-x-4 gap-y-7">
                    <Link v-for="m in items" :key="m.id" :href="route('productos.index', { vista: 'marcas', marca: m.id })"
                        class="group flex flex-col items-center">
                        <div class="relative">
                            <!-- Sin margen y con object-cover: el archivo ya viene
                                 cuadrado desde el servidor (ver MarcaObserver), así
                                 que el logo llena el círculo tal como se recortó.
                                 El margen que había antes lo hacía verse chico,
                                 con un anillo blanco alrededor. -->
                            <div class="w-32 h-32 sm:w-36 sm:h-36 rounded-full overflow-hidden bg-surface-1 ring-1 ring-border group-hover:ring-accent/50 shadow-sm group-hover:shadow-md transition-all duration-300">
                                <img v-if="m.logo_url" :src="m.logo_url" :alt="m.nombre"
                                    class="w-full h-full object-cover group-hover:scale-[1.06] transition-transform duration-500" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <span class="text-4xl font-bold text-surface-4">{{ m.nombre.charAt(0) }}</span>
                                </div>
                            </div>
                            <span class="absolute bottom-1 right-1 bg-surface-1/95 backdrop-blur-sm text-text-secondary text-[11px] font-semibold px-2 py-0.5 rounded-full shadow-sm ring-1 ring-border">{{ m.productos_count }}</span>
                        </div>
                        <p class="text-[15px] font-semibold text-text mt-3 text-center leading-snug group-hover:text-accent transition">{{ m.nombre }}</p>
                    </Link>
                </div>
            </template>

            <!-- Categories in brand -->
            <template v-else-if="modo === 'categorias_en_marca'">
                <div class="flex items-center gap-4 mb-1">
                    <div v-if="marcaActual?.logo_url" class="w-14 h-14 rounded-full bg-surface-1 ring-1 ring-border flex items-center justify-center p-2 shrink-0">
                        <img :src="marcaActual.logo_url" :alt="marcaActual.nombre" class="max-w-full max-h-full object-contain" />
                    </div>
                    <h1 class="text-xl font-semibold text-text">{{ marcaActual?.nombre }}</h1>
                </div>
                <p class="text-[13px] text-text-muted mb-6">{{ items.length }} categorías</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-x-4 gap-y-7">
                    <Link v-for="cat in items" :key="cat.id" :href="route('productos.index', { vista: 'marcas', marca: marcaActual.id, categoria: cat.id })"
                        class="group flex flex-col items-center">
                        <div class="relative">
                            <div class="w-32 h-32 sm:w-36 sm:h-36 rounded-full overflow-hidden bg-surface-3 ring-1 ring-border group-hover:ring-accent/50 shadow-sm group-hover:shadow-md transition-all duration-300">
                                <img v-if="cat.imagen_url" :src="cat.imagen_url" :alt="cat.nombre"
                                    class="w-full h-full object-cover group-hover:scale-[1.06] transition-transform duration-500" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <span class="text-4xl font-bold text-surface-4">{{ cat.nombre.charAt(0) }}</span>
                                </div>
                            </div>
                            <span class="absolute bottom-1 right-1 bg-surface-1/95 backdrop-blur-sm text-text-secondary text-[11px] font-semibold px-2 py-0.5 rounded-full shadow-sm ring-1 ring-border">{{ cat.productos_count }}</span>
                        </div>
                        <p class="text-[15px] font-semibold text-text mt-3 text-center leading-snug group-hover:text-accent transition">{{ cat.nombre }}</p>
                    </Link>
                </div>

                <!-- Los productos de la marca, acá mismo: las categorías de
                     arriba siguen sirviendo para filtrar, pero ya no hace falta
                     entrar a una para ver qué hay. -->
                <div v-if="productos?.data?.length" class="mt-12">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-0.5 h-6 rounded-full bg-accent"></div>
                        <h2 class="text-[15px] font-semibold text-text">Todos los productos</h2>
                        <span class="text-[11px] text-accent bg-accent/10 px-2 py-0.5 rounded-lg">{{ productos.total }}</span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4">
                        <ProductCard v-for="p in productos.data" :key="p.id" :producto="p" @image-click="modalImage = $event" />
                    </div>
                    <Pagination :links="productos.links" />
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
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4">
                        <ProductCard v-for="p in g.productos" :key="p.id" :producto="p" @image-click="modalImage = $event" />
                    </div>
                </div>
            </template>

            <!-- Products flat -->
            <template v-else-if="modo === 'productos' && productos">
                <div v-if="productos.data.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4">
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
