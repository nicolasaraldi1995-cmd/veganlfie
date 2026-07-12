<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ items: Array, total: Number });
const MIN = 300000; const FREE = 450000;
const progress = computed(() => Math.min((props.total / FREE) * 100, 100));
const canBuy = computed(() => props.total >= MIN);
function updateQty(id, q) { router.patch(route('cart.update'), { presentacion_id: id, cantidad: q }, { preserveScroll: true }); }
function remove(id) { router.delete(route('cart.remove'), { data: { presentacion_id: id }, preserveScroll: true }); }
</script>
<template>
    <Head title="Carrito" />
    <PublicLayout>
        <div class="max-w-3xl mx-auto px-6 py-8">
            <h1 class="text-xl font-semibold text-text mb-6">Tu carrito</h1>
            <div v-if="items.length">
                <div class="bg-surface-1 rounded-2xl border border-border p-5 mb-6">
                    <div class="flex justify-between text-[11px] mb-2">
                        <span v-if="!canBuy" class="text-red-400">Faltan ${{ (MIN - total).toLocaleString('es-AR') }} (mín ${{ MIN.toLocaleString('es-AR') }})</span>
                        <span v-else-if="total < FREE" class="text-accent">${{ (FREE - total).toLocaleString('es-AR') }} para envío gratis</span>
                        <span v-else class="text-accent">Envío gratis</span>
                    </div>
                    <div class="w-full bg-surface-3 rounded-full h-1"><div class="h-1 rounded-full bg-accent transition-all duration-700" :style="{ width: progress + '%' }"></div></div>
                </div>
                <div class="bg-surface-1 rounded-2xl border border-border divide-y divide-border">
                    <div v-for="item in items" :key="item.presentacion_id" class="p-5 flex items-center gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-medium text-text truncate">{{ item.nombre }}</p>
                            <p class="text-[11px] text-text-muted">{{ item.marca }} · {{ item.categoria }} · {{ item.unidad }}</p>
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
                <div class="mt-6 bg-surface-1 rounded-2xl border border-border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-text-secondary">Total</span>
                        <span class="text-xl font-semibold text-text">${{ total.toLocaleString('es-AR') }}</span>
                    </div>
                    <div class="flex gap-3">
                        <Link :href="route('productos.index')" class="flex-1 text-center border border-border text-text-secondary py-3 rounded-xl hover:bg-surface-2 hover:text-text transition text-[13px] font-medium">Seguir comprando</Link>
                        <Link v-if="canBuy" :href="route('checkout.index')" class="flex-1 text-center bg-accent hover:bg-accent-bright text-white py-3 rounded-xl transition text-[13px] font-medium">Finalizar compra</Link>
                        <span v-else class="flex-1 text-center bg-surface-3 text-text-muted py-3 rounded-xl text-[13px]">Mínimo ${{ MIN.toLocaleString('es-AR') }}</span>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-20">
                <p class="text-text-muted mb-4">Carrito vacío</p>
                <Link :href="route('productos.index')" class="text-accent hover:text-accent-bright text-sm transition">Explorar productos</Link>
            </div>
        </div>
    </PublicLayout>
</template>
