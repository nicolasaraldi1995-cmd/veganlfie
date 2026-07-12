<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ProductRow from '@/Components/ProductRow.vue';
import ImageModal from '@/Components/ImageModal.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
defineProps({ porMarca: Array, totalOfertas: Number });
const modalImage = ref(null);
</script>
<template>
    <Head title="Ofertas" />
    <PublicLayout>
        <div class="px-6 py-8">
            <div class="flex items-center gap-3 mb-8">
                <span class="w-8 h-8 rounded-xl bg-red-500/10 flex items-center justify-center text-red-400 text-sm font-bold">%</span>
                <h1 class="text-xl font-semibold text-text">Ofertas</h1>
                <span class="text-[11px] text-text-muted bg-surface-2 px-2 py-0.5 rounded-lg">{{ totalOfertas }} productos</span>
            </div>

            <div v-if="porMarca.length">
                <div v-for="grupo in porMarca" :key="grupo.marca" class="mb-10">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-1 h-6 rounded-full bg-red-400"></div>
                        <h2 class="text-[15px] font-semibold text-text">{{ grupo.marca }}</h2>
                        <span class="text-[11px] text-text-muted bg-surface-2 px-2 py-0.5 rounded-lg">{{ grupo.total }}</span>
                    </div>
                    <ProductRow :productos="grupo.productos" @image-click="modalImage = $event" />
                </div>
            </div>
            <div v-else class="text-center py-20 text-text-muted">No hay ofertas activas.</div>
        </div>
        <ImageModal :src="modalImage" @close="modalImage = null" />
    </PublicLayout>
</template>
