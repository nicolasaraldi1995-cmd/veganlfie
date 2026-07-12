<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ProductCard from '@/Components/ProductCard.vue';
import ImageModal from '@/Components/ImageModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
const props = defineProps({ pedido: Object, recomendados: Array });
const modalImage = ref(null);
const editable = computed(() => props.pedido.estado === 'pending');
const estados = { pending:{l:'Pendiente',c:'text-amber-400 bg-amber-400/10'}, confirmed:{l:'Confirmado',c:'text-green-400 bg-green-400/10'}, preparing:{l:'En preparación',c:'text-blue-400 bg-blue-400/10'}, shipped:{l:'Enviado',c:'text-indigo-400 bg-indigo-400/10'}, delivered:{l:'Entregado',c:'text-accent bg-accent/10'}, canceled:{l:'Cancelado',c:'text-red-400 bg-red-400/10'} };
function updateQty(id, q) { router.patch(route('pedido.update-item', props.pedido.id), { presentacion_id: id, cantidad: q }, { preserveScroll: true }); }
function removeItem(id) { router.delete(route('pedido.remove-item', props.pedido.id), { data: { presentacion_id: id }, preserveScroll: true }); }
</script>
<template>
    <Head :title="`Pedido #${pedido.id}`" />
    <PublicLayout>
        <div class="max-w-5xl mx-auto px-6 py-8">
            <nav class="text-[13px] text-text-muted mb-6"><Link :href="route('home')" class="hover:text-accent transition">Inicio</Link><span class="mx-2 text-surface-4">/</span><Link :href="route('mis-pedidos')" class="hover:text-accent transition">Mis pedidos</Link><span class="mx-2 text-surface-4">/</span><span class="text-text">#{{ pedido.id }}</span></nav>
            <div class="flex items-center gap-3 mb-6">
                <h1 class="text-xl font-semibold text-text">Pedido #{{ pedido.id }}</h1>
                <span class="text-[11px] px-2.5 py-1 rounded-lg font-medium" :class="estados[pedido.estado]?.c">{{ estados[pedido.estado]?.l }}</span>
                <span v-if="editable" class="text-[10px] text-accent bg-accent/10 px-2 py-0.5 rounded-lg">Editable</span>
                <a :href="route('pedido.pdf', pedido.id)" class="ml-auto text-[12px] text-accent hover:text-accent-bright transition flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Descargar PDF
                </a>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <div class="lg:col-span-3 space-y-6">
                    <div class="bg-surface-1 rounded-2xl border border-border">
                        <div class="px-6 py-4 border-b border-border"><h2 class="font-medium text-text">Productos</h2></div>
                        <div class="divide-y divide-border">
                            <div v-for="it in pedido.items" :key="it.id" class="px-6 py-4 flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-medium text-text truncate">{{ it.presentacion.producto.nombre }}</p>
                                    <p class="text-[11px] text-text-muted">{{ it.presentacion.producto.marca.nombre }} · {{ it.presentacion.unidad }} · ${{ parseFloat(it.precio_unitario).toLocaleString('es-AR') }} c/u</p>
                                </div>
                                <template v-if="editable">
                                    <div class="flex items-center bg-surface-3 rounded-lg shrink-0">
                                        <button @click="updateQty(it.presentacion_id, it.cantidad-1)" class="w-7 h-7 flex items-center justify-center text-text-muted hover:text-text text-xs transition">−</button>
                                        <span class="w-6 h-7 flex items-center justify-center text-[12px] font-semibold text-text">{{ it.cantidad }}</span>
                                        <button @click="updateQty(it.presentacion_id, it.cantidad+1)" class="w-7 h-7 flex items-center justify-center text-text-muted hover:text-text text-xs transition">+</button>
                                    </div>
                                    <button @click="removeItem(it.presentacion_id)" class="text-text-muted hover:text-red-400 transition"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                </template>
                                <span v-else class="text-[13px] text-text-secondary shrink-0">x{{ it.cantidad }}</span>
                                <p class="text-[13px] font-semibold text-text w-20 text-right shrink-0">${{ parseFloat(it.subtotal).toLocaleString('es-AR') }}</p>
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t border-border flex justify-between">
                            <Link v-if="editable" :href="route('productos.index')" class="text-[13px] text-accent hover:text-accent-bright transition">+ Agregar más</Link><span v-else></span>
                            <span class="font-semibold text-lg text-text">${{ parseFloat(pedido.total).toLocaleString('es-AR') }}</span>
                        </div>
                    </div>
                    <div v-if="editable && recomendados.length">
                        <div class="flex items-center gap-3 mb-5"><div class="w-0.5 h-5 rounded-full bg-accent"></div><h2 class="text-[15px] font-semibold text-text">Agregá a tu pedido</h2></div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3"><ProductCard v-for="p in recomendados" :key="p.id" :producto="p" @image-click="modalImage=$event" /></div>
                    </div>
                </div>
                <div class="lg:col-span-2"><div class="sticky top-20 space-y-4">
                    <div v-if="pedido.datos_cliente" class="bg-surface-1 rounded-2xl border border-border p-6">
                        <h2 class="font-medium text-text mb-3">Datos de entrega</h2>
                        <div class="space-y-1 text-[13px] text-text-secondary">
                            <p class="font-medium text-text">{{ pedido.datos_cliente.nombre }}</p>
                            <p v-if="pedido.datos_cliente.negocio">{{ pedido.datos_cliente.negocio }}</p>
                            <p>{{ pedido.datos_cliente.celular }}</p><p>{{ pedido.datos_cliente.direccion }}, {{ pedido.datos_cliente.ciudad }}</p>
                            <p class="text-[11px] mt-2">Entrega: {{ pedido.datos_cliente.entrega==='retiro'?'Retiro en local':'Envío a domicilio' }}</p>
                        </div>
                    </div>
                    <div class="bg-surface-1 rounded-2xl border border-border p-6">
                        <p class="text-[13px] text-text-secondary">{{ new Date(pedido.created_at).toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' }) }}</p>
                        <p v-if="editable" class="text-[12px] text-accent bg-accent/10 p-3 rounded-xl mt-3">Podés modificar hasta que lo confirmemos.</p>
                    </div>
                </div></div>
            </div>
        </div>
        <ImageModal :src="modalImage" @close="modalImage=null" />
    </PublicLayout>
</template>
