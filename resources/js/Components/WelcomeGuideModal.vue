<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const emit = defineEmits(['close']);

const demo = [
    { unidad: '90ml', precio: '1.849' },
    { unidad: '180ml', precio: '2.931' },
    { unidad: '360ml', precio: '5.213' },
];
const demoIndex = ref(0);
let interval = null;

function onKeydown(e) {
    if (e.key === 'Escape') emit('close');
}

onMounted(() => {
    interval = setInterval(() => {
        demoIndex.value = (demoIndex.value + 1) % demo.length;
    }, 1300);
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    clearInterval(interval);
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4" @click.self="emit('close')">
        <div class="bg-surface-1 rounded-2xl border border-border shadow-2xl max-w-sm w-full p-6 relative">
            <button @click="emit('close')" class="absolute top-4 right-4 text-text-muted hover:text-text transition" aria-label="Cerrar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <h3 class="text-[16px] font-semibold text-text mb-1 pr-6">¡Bienvenido a VEGANLIFE!</h3>
            <p class="text-[13px] text-text-secondary leading-relaxed mb-4">En los productos con varias medidas, tocá las presentaciones para ver cómo cambia el precio.</p>

            <div class="bg-surface-2 rounded-xl border border-border p-4">
                <p class="brand-label text-accent-dim mb-1">CHÍA GRAAL</p>
                <p class="text-[13px] font-bold text-text mb-3">Aceite de coco neutro</p>

                <div class="flex gap-1.5 mb-3">
                    <span v-for="(p, i) in demo" :key="p.unidad"
                        class="px-2.5 py-1 text-[11px] font-semibold rounded-lg transition-all duration-300"
                        :class="i === demoIndex ? 'bg-accent text-white shadow-sm shadow-accent/30 scale-105' : 'bg-surface-3 text-text-secondary'">
                        {{ p.unidad }}
                    </span>
                </div>

                <Transition name="price-fade" mode="out-in">
                    <p :key="demoIndex" class="text-2xl price-display text-text">${{ demo[demoIndex].precio }}</p>
                </Transition>
            </div>
        </div>
    </div>
</template>

<style scoped>
.price-fade-enter-active, .price-fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.price-fade-enter-from { opacity: 0; transform: translateY(2px); }
.price-fade-leave-to { opacity: 0; transform: translateY(-2px); }
</style>
