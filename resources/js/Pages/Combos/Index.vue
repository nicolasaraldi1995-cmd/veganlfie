<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ComboDetailModal from '@/Components/ComboDetailModal.vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
defineProps({ combos: Object });
const comboSeleccionado = ref(null);
function addCombo(id) {
    router.post(route('cart.add-combo'), { combo_id: id }, { preserveScroll: true });
}
</script>
<template>
    <Head title="Combos" />
    <PublicLayout>
        <div class="px-6 py-8">
            <h1 class="text-xl font-semibold text-text mb-6">Combos</h1>
            <div v-if="combos.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="c in combos.data" :key="c.id" @click="comboSeleccionado = c" class="cursor-pointer bg-surface-2 rounded-2xl border border-border overflow-hidden hover:border-border-hover transition-all duration-300">
                    <div class="relative aspect-video bg-surface-3">
                        <img v-if="c.imagen_url" :src="c.imagen_url" loading="lazy" class="w-full h-full object-cover" />
                        <span class="absolute top-2.5 left-2.5 bg-purple-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-lg">COMBO</span>
                    </div>
                    <div class="p-5">
                        <h3 class="font-medium text-text">{{ c.nombre }}</h3>
                        <p v-if="c.descripcion" class="text-sm text-text-muted mt-1">{{ c.descripcion }}</p>
                        <div v-if="c.items?.length" class="mt-3 space-y-1">
                            <p v-for="item in c.items" :key="item.id" class="text-[11px] text-text-muted">{{ item.cantidad }}x {{ item.presentacion?.producto?.nombre }} ({{ item.presentacion?.unidad }})</p>
                        </div>
                        <div class="flex items-center justify-between mt-4">
                            <div>
                                <del v-if="c.descuento_porcentaje && c.precio_sin_descuento !== c.precio_final" class="text-[12px] text-text-muted">${{ Math.round(c.precio_sin_descuento).toLocaleString('es-AR') }}</del>
                                <span v-if="c.descuento_porcentaje" class="text-[11px] text-red-400 ml-1">-{{ c.descuento_porcentaje }}%</span>
                                <p class="text-lg font-semibold text-text">${{ Math.round(c.precio_final).toLocaleString('es-AR') }}</p>
                            </div>
                            <button @click.stop="addCombo(c.id)" class="bg-accent hover:bg-accent-bright text-white text-[12px] font-semibold px-4 py-2 rounded-lg transition-all active:scale-[0.98]">
                                Agregar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-20 text-text-muted">Sin combos.</div>
            <Pagination :links="combos.links" />
        </div>
        <ComboDetailModal :combo="comboSeleccionado" @close="comboSeleccionado = null" />
    </PublicLayout>
</template>
