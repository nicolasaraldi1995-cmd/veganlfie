<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import Pagination from '@/Components/Pagination.vue';
import ImageModal from '@/Components/ImageModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
defineProps({ marca: Object, productos: Object });
const modalImage = ref(null);
</script>
<template>
    <Head :title="marca.nombre" />
    <PublicLayout>
        <div class="px-6 py-8">
            <nav class="text-[13px] text-text-muted mb-6"><Link :href="route('home')" class="hover:text-accent transition">Inicio</Link><span class="mx-2 text-surface-4">/</span><span class="text-text">{{ marca.nombre }}</span></nav>
            <div class="flex items-center gap-4 mb-6">
                <img v-if="marca.logo_url" :src="marca.logo_url" class="h-12 object-contain opacity-70" />
                <h1 class="text-xl font-semibold text-text">{{ marca.nombre }}</h1>
                <span class="text-[13px] text-text-muted ml-auto">{{ productos.total }} productos</span>
            </div>
            <div v-if="productos.data.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"><ProductCard v-for="p in productos.data" :key="p.id" :producto="p" @image-click="modalImage = $event" /></div>
            <div v-else class="text-center py-20 text-text-muted">Sin productos.</div>
            <Pagination :links="productos.links" />
        </div>
        <ImageModal :src="modalImage" @close="modalImage = null" />
    </PublicLayout>
</template>
