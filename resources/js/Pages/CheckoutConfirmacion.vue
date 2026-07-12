<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ pedido: Object });
</script>
<template>
    <Head title="Pedido confirmado" />
    <PublicLayout>
        <div class="max-w-2xl mx-auto px-6 py-12 text-center">
            <div class="bg-surface-1 rounded-2xl border border-border p-10">
                <div class="w-14 h-14 bg-accent/10 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <svg class="w-7 h-7 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h1 class="text-xl font-semibold text-text mb-2">Pedido registrado</h1>
                <p class="text-text-secondary mb-8">Tu pedido <span class="font-semibold text-text">#{{ pedido.id }}</span> quedó pendiente de confirmación.</p>
                <div class="text-left bg-surface-2 rounded-xl p-5 mb-6">
                    <h3 class="font-medium text-text mb-3 text-[13px]">Detalle</h3>
                    <div class="space-y-2">
                        <div v-for="it in pedido.items" :key="it.id" class="flex justify-between text-[13px]">
                            <span class="text-text-secondary">
                                {{ it.presentacion?.producto?.nombre ?? 'Producto no disponible' }} ({{ it.presentacion?.unidad }}) x{{ it.cantidad }}
                                <span v-if="it.presentacion?.producto?.congelado" class="inline-flex items-center gap-0.5 ml-1 text-[10px] text-sky-400 bg-sky-500/10 px-1.5 py-0.5 rounded-md align-middle">❄</span>
                            </span>
                            <span class="text-text font-medium">${{ parseFloat(it.subtotal).toLocaleString('es-AR') }}</span>
                        </div>
                    </div>
                    <div v-if="pedido.items.some(it => it.presentacion?.producto?.congelado)" class="mt-3 bg-sky-500/5 border border-sky-500/15 rounded-lg px-3 py-2">
                        <p class="text-[11px] text-sky-400">❄ Tu pedido incluye productos congelados. Confirmaremos disponibilidad para tu zona.</p>
                    </div>
                    <div class="h-px bg-border my-3"></div>
                    <div class="flex justify-between font-semibold text-text"><span>Total</span><span>${{ parseFloat(pedido.total).toLocaleString('es-AR') }}</span></div>
                </div>
                <Link :href="route('home')" class="inline-block bg-accent hover:bg-accent-bright text-white px-6 py-2.5 rounded-xl font-medium text-[13px] transition">Volver al inicio</Link>
            </div>
        </div>
    </PublicLayout>
</template>
