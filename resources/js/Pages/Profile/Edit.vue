<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    cliente: Object,
    totalPedidos: Number,
    pedidosRecientes: Array,
    productosFrecuentes: Array,
});

const estados = {
    pending: { l: 'Pendiente', c: 'text-amber-400 bg-amber-400/10' },
    confirmed: { l: 'Confirmado', c: 'text-green-400 bg-green-400/10' },
    preparing: { l: 'En preparación', c: 'text-blue-400 bg-blue-400/10' },
    shipped: { l: 'Enviado', c: 'text-indigo-400 bg-indigo-400/10' },
    delivered: { l: 'Entregado', c: 'text-accent bg-accent/10' },
    canceled: { l: 'Cancelado', c: 'text-red-400 bg-red-400/10' },
};
</script>

<template>
    <Head title="Mi cuenta" />

    <PublicLayout>
        <div class="max-w-3xl mx-auto px-6 py-8 space-y-6">
            <div>
                <h1 class="text-xl font-semibold text-text">Mi cuenta</h1>
                <p class="text-[13px] text-text-muted mt-1">Hola {{ cliente.nombre }}, este es tu resumen en VEGANLIFE.</p>
            </div>

            <!-- Datos de la cuenta (solo lectura) -->
            <div class="bg-surface-1 rounded-2xl border border-border p-6">
                <h2 class="text-[15px] font-semibold text-text mb-4">Datos de la cuenta</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-[13px]">
                    <div><span class="text-text-muted">Nombre</span><p class="text-text mt-0.5">{{ cliente.nombre }}</p></div>
                    <div v-if="cliente.negocio"><span class="text-text-muted">Negocio</span><p class="text-text mt-0.5">{{ cliente.negocio }}</p></div>
                    <div><span class="text-text-muted">Email</span><p class="text-text mt-0.5">{{ cliente.email }}</p></div>
                    <div><span class="text-text-muted">Celular</span><p class="text-text mt-0.5">{{ cliente.celular }}</p></div>
                    <div><span class="text-text-muted">Dirección</span><p class="text-text mt-0.5">{{ cliente.direccion }}</p></div>
                    <div><span class="text-text-muted">Ciudad</span><p class="text-text mt-0.5">{{ cliente.ciudad }}<span v-if="cliente.provincia">, {{ cliente.provincia }}</span></p></div>
                </div>
                <p class="text-[12px] text-text-muted mt-4 leading-relaxed">¿Necesitás corregir algún dato? Escribinos por WhatsApp y lo actualizamos por vos.</p>
            </div>

            <!-- Pedidos recientes -->
            <div class="bg-surface-1 rounded-2xl border border-border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[15px] font-semibold text-text">Tus pedidos</h2>
                    <span class="text-[11px] text-text-muted bg-surface-2 px-2 py-0.5 rounded-lg">{{ totalPedidos }}</span>
                </div>

                <div v-if="pedidosRecientes.length" class="space-y-2">
                    <Link v-for="p in pedidosRecientes" :key="p.id" :href="route('pedido.show', p.id)"
                        class="flex items-center justify-between px-4 py-3 rounded-xl bg-surface-2 hover:bg-surface-3 transition">
                        <div class="flex items-center gap-3">
                            <span class="text-[13px] font-semibold text-text">#{{ p.id }}</span>
                            <span class="text-[11px] px-2 py-0.5 rounded-lg font-medium" :class="estados[p.estado]?.c || 'text-text-muted bg-surface-3'">{{ estados[p.estado]?.l || p.estado }}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-[13px] font-semibold text-text">${{ p.total.toLocaleString('es-AR') }}</span>
                            <span class="text-[11px] text-text-muted">{{ p.fecha }}</span>
                        </div>
                    </Link>
                    <Link :href="route('mis-pedidos')" class="block text-center text-[13px] text-accent hover:text-accent-bright transition pt-2">Ver todos mis pedidos →</Link>
                </div>
                <div v-else class="text-center py-8">
                    <p class="text-text-muted text-[13px] mb-3">Todavía no hiciste ningún pedido.</p>
                    <Link :href="route('productos.index')" class="text-accent text-[13px] hover:text-accent-bright transition">Explorar productos</Link>
                </div>
            </div>

            <!-- Productos más comprados -->
            <div v-if="productosFrecuentes.length" class="bg-surface-1 rounded-2xl border border-border p-6">
                <h2 class="text-[15px] font-semibold text-text mb-4">Tus productos más comprados</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <Link v-for="p in productosFrecuentes" :key="p.id" :href="route('productos.show', p.slug)"
                        class="group">
                        <div class="aspect-square rounded-xl bg-surface-2 overflow-hidden mb-2">
                            <img v-if="p.imagen_url" :src="p.imagen_url" :alt="p.nombre" loading="lazy" class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300" />
                        </div>
                        <p class="text-[11px] text-accent-dim truncate">{{ p.marca?.nombre }}</p>
                        <p class="text-[12px] font-medium text-text line-clamp-2 leading-snug">{{ p.nombre }}</p>
                    </Link>
                </div>
            </div>

            <!-- Cambiar contraseña -->
            <div class="bg-surface-1 rounded-2xl border border-border p-6">
                <UpdatePasswordForm />
            </div>
        </div>
    </PublicLayout>
</template>
