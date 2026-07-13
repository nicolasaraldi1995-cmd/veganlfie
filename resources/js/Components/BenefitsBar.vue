<script setup>
const benefits = [
    {
        title: 'Envíos por zona',
        subtitle: 'Coordinado por WhatsApp',
        icon: 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 0h-12',
    },
    {
        title: 'Efectivo, transferencia y MercadoPago',
        subtitle: 'Elegís cómo pagar',
        icon: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
    },
    {
        title: 'Venta mayorista y minorista',
        subtitle: 'Precios especiales por volumen',
        icon: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    },
    {
        title: '100% productos veganos',
        subtitle: 'Naturales, sin crueldad animal',
        icon: 'M12 21a9 9 0 100-18 9 9 0 000 18zm0 0c1.5-3 1.5-15 0-18m0 18c-1.5-3-1.5-15 0-18m-6 9h12',
    },
];
</script>

<template>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div v-for="b in benefits" :key="b.title" class="flex items-start gap-3 bg-surface-1 border border-border rounded-xl px-4 py-3.5">
            <span class="w-9 h-9 rounded-lg bg-accent/10 text-accent flex items-center justify-center shrink-0">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="b.icon"/>
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-[12.5px] font-semibold text-text leading-snug">{{ b.title }}</p>
                <p class="text-[11px] text-text-muted mt-0.5">{{ b.subtitle }}</p>
            </div>
        </div>
    </div>
</template>
