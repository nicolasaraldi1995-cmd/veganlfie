<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import Pagination from '@/Components/Pagination.vue';
import ImageModal from '@/Components/ImageModal.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
defineProps({ productos: Object });
const modalImage = ref(null);
</script>
<template>
    <Head title="Marca VEGANLIFE" />
    <PublicLayout>
        <div class="px-6 py-8">
            <div class="flex items-center gap-3 mb-6">
                <span class="w-8 h-8 rounded-xl bg-accent/10 flex items-center justify-center">
                    <img src="/images/logo.png" class="h-5 opacity-80" />
                </span>
                <h1 class="text-xl font-semibold text-text">Marca VEGANLIFE</h1>
                <span class="text-[11px] text-text-muted bg-surface-2 px-2 py-0.5 rounded-lg">{{ productos.total }}</span>
            </div>
            <div v-if="productos.data.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <ProductCard v-for="p in productos.data" :key="p.id" :producto="p" @image-click="modalImage = $event" />
            </div>
            <div v-else class="text-center py-20 text-text-muted">No hay productos VEGANLIFE.</div>
            <Pagination :links="productos.links" />
        </div>
        <ImageModal :src="modalImage" @close="modalImage = null" />
    </PublicLayout>
</template>
