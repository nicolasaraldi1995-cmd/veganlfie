<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
defineProps({ pedidos: Object });
const expanded = ref(null);
const estados = { pending:{l:'Pendiente',c:'text-amber-400 bg-amber-400/10'}, confirmed:{l:'Confirmado',c:'text-green-400 bg-green-400/10'}, preparing:{l:'En preparación',c:'text-blue-400 bg-blue-400/10'}, shipped:{l:'Enviado',c:'text-indigo-400 bg-indigo-400/10'}, delivered:{l:'Entregado',c:'text-accent bg-accent/10'}, canceled:{l:'Cancelado',c:'text-red-400 bg-red-400/10'} };
</script>
<template>
    <Head title="Mis pedidos" />
    <PublicLayout>
        <div class="max-w-3xl mx-auto px-6 py-8">
            <h1 class="text-xl font-semibold text-text mb-6">Mis pedidos</h1>
            <div v-if="pedidos.data.length" class="space-y-3">
                <div v-for="p in pedidos.data" :key="p.id" class="bg-surface-1 rounded-2xl border border-border overflow-hidden">
                    <div class="px-6 py-4 flex items-center justify-between cursor-pointer hover:bg-surface-2/50 transition" @click="expanded = expanded===p.id?null:p.id">
                        <div class="flex items-center gap-3">
                            <span class="text-[13px] font-semibold text-text">#{{ p.id }}</span>
                            <span class="text-[11px] px-2 py-0.5 rounded-lg font-medium" :class="estados[p.estado]?.c || 'text-text-muted bg-surface-3'">{{ estados[p.estado]?.l || p.estado }}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-[13px] font-semibold text-text">${{ parseFloat(p.total).toLocaleString('es-AR') }}</span>
                            <span class="text-[11px] text-text-muted">{{ new Date(p.created_at).toLocaleDateString('es-AR') }}</span>
                            <svg class="w-4 h-4 text-text-muted transition-transform" :class="expanded===p.id?'rotate-180':''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </div>
                    <div v-if="expanded===p.id" class="px-6 pb-5 border-t border-border pt-4">
                        <div class="space-y-2">
                            <div v-for="it in p.items" :key="it.id" class="flex justify-between text-[13px]">
                                <span class="text-text">{{ it.presentacion?.producto?.nombre ?? 'Producto no disponible' }} <span class="text-text-muted">({{ it.presentacion?.unidad }}) x{{ it.cantidad }}</span></span>
                                <span class="font-medium text-text">${{ parseFloat(it.subtotal).toLocaleString('es-AR') }}</span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link :href="route('pedido.show', p.id)" class="text-[13px] font-medium px-4 py-2 rounded-xl transition-all"
                                :class="p.estado==='pending'?'bg-accent text-white hover:bg-accent-bright':'bg-surface-3 text-text-secondary hover:bg-surface-4 hover:text-text'">
                                {{ p.estado==='pending'?'Editar pedido':'Ver detalle' }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-20 text-text-muted">
                <p class="mb-3">Sin pedidos todavía.</p>
                <Link :href="route('productos.index')" class="text-accent text-sm hover:text-accent-bright transition">Explorar productos</Link>
            </div>
            <Pagination :links="pedidos.links" />
        </div>
    </PublicLayout>
</template>
