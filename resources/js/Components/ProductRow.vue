<script setup>
import ProductCard from '@/Components/ProductCard.vue';
import { ref } from 'vue';

defineProps({ productos: Array });
const emit = defineEmits(['imageClick']);

const scrollContainer = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(true);

function updateScroll() {
    const el = scrollContainer.value;
    if (!el) return;
    canScrollLeft.value = el.scrollLeft > 10;
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 10;
}

function scroll(dir) {
    const el = scrollContainer.value;
    if (!el) return;
    const cardWidth = el.querySelector(':first-child')?.offsetWidth || 250;
    el.scrollBy({ left: dir * cardWidth * 2, behavior: 'smooth' });
}
</script>

<template>
    <div class="relative group/row">
        <!-- Left arrow -->
        <button v-show="canScrollLeft" @click="scroll(-1)"
            class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-surface-1/90 backdrop-blur-sm border border-border text-text-secondary hover:text-text hover:bg-surface-2 transition-all opacity-0 group-hover/row:opacity-100 -translate-x-1 flex items-center justify-center shadow-lg shadow-black/30">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>

        <!-- Scrollable row -->
        <div ref="scrollContainer" @scroll="updateScroll"
            class="flex gap-4 overflow-x-auto scroll-smooth pb-2 scrollbar-hide"
            style="-ms-overflow-style: none; scrollbar-width: none;">
            <div v-for="p in productos" :key="p.id" class="w-[220px] shrink-0 first:ml-0">
                <ProductCard :producto="p" @image-click="emit('imageClick', $event)" />
            </div>
        </div>

        <!-- Right arrow -->
        <button v-show="canScrollRight" @click="scroll(1)"
            class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-surface-1/90 backdrop-blur-sm border border-border text-text-secondary hover:text-text hover:bg-surface-2 transition-all opacity-0 group-hover/row:opacity-100 translate-x-1 flex items-center justify-center shadow-lg shadow-black/30">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

        <!-- Fade edges -->
        <div v-show="canScrollLeft" class="absolute left-0 top-0 bottom-2 w-12 bg-gradient-to-r from-surface to-transparent pointer-events-none z-[5]"></div>
        <div v-show="canScrollRight" class="absolute right-0 top-0 bottom-2 w-12 bg-gradient-to-l from-surface to-transparent pointer-events-none z-[5]"></div>
    </div>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
</style>
