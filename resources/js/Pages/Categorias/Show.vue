<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import Pagination from '@/Components/Pagination.vue';
import ImageModal from '@/Components/ImageModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
defineProps({ categoria: Object, productos: Object });
const modalImage = ref(null);
</script>
<template>
    <Head :title="categoria.nombre" />
    <PublicLayout>
        <div class="px-6 py-8">
            <nav class="text-[13px] text-text-muted mb-6"><Link :href="route('home')" class="hover:text-accent transition">Inicio</Link><span class="mx-2 text-surface-4">/</span><span class="text-text">{{ categoria.nombre }}</span></nav>
            <h1 class="text-xl font-semibold text-text mb-2">{{ categoria.nombre }}</h1>
            <p class="text-[13px] text-text-muted mb-6">{{ productos.total }} productos</p>
            <div v-if="productos.data.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"><ProductCard v-for="p in productos.data" :key="p.id" :producto="p" @image-click="modalImage = $event" /></div>
            <div v-else class="text-center py-20 text-text-muted">Sin productos.</div>
            <Pagination :links="productos.links" />
        </div>
        <ImageModal :src="modalImage" @close="modalImage = null" />
    </PublicLayout>
</template>
