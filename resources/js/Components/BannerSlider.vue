<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({ banners: { type: Array, default: () => [] } });

const current = ref(0);
const total = computed(() => props.banners.length);

let timer = null;
let touchStart = 0;

function next() { current.value = (current.value + 1) % total.value; resetTimer(); }
function prev() { current.value = (current.value - 1 + total.value) % total.value; resetTimer(); }
function goTo(i) { current.value = i; resetTimer(); }
function resetTimer() { clearInterval(timer); timer = setInterval(next, 5000); }
function onTouchStart(e) { touchStart = e.touches[0].clientX; }
function onTouchEnd(e) {
    const diff = touchStart - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) diff > 0 ? next() : prev();
}

onMounted(() => { if (total.value > 1) timer = setInterval(next, 5000); });
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div v-if="banners.length" class="relative group" @touchstart="onTouchStart" @touchend="onTouchEnd">
        <!-- Ancho completo de la pantalla, siempre. La proporción 3:1 define el
             alto, con tope de 380px para que en un monitor no se coma media
             pantalla. La imagen nunca se recorta (object-contain) y lo que
             sobre lo cubre el fondo desenfocado.
             w-full es necesario: con aspect-ratio y max-height juntos, el
             navegador achica el ancho para respetar la proporción en vez de
             estirarse. Fijando el ancho, lo que cede es el alto. -->
        <div class="relative w-full overflow-hidden bg-surface-2 aspect-[3/1] max-h-[380px] rounded-2xl border border-border shadow-sm">
            <!-- Fundido con opacidad, sin TransitionGroup: combinado con v-show
                 las clases de la animación quedaban pegadas y varias diapositivas
                 se quedaban en opacidad 0 para siempre, o sea el banner en blanco.
                 Así la que está activa vale 1 y el resto 0, sin estados a medias. -->
            <div v-for="(b, i) in banners" :key="b.id"
                class="absolute inset-0 transition-opacity duration-500 ease-out"
                :class="i === current ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                :aria-hidden="i !== current">
                <!-- Relleno: la misma imagen agrandada y desenfocada. Tapa el
                     hueco cuando la foto no tiene la forma exacta de la tira,
                     en vez de dejar franjas grises a los costados. -->
                <img :src="b.imagen" alt="" aria-hidden="true"
                    class="absolute inset-0 w-full h-full object-cover scale-125 blur-2xl opacity-70" />
                <a v-if="b.url" :href="b.url" class="relative block w-full h-full">
                    <img :src="b.imagen" :alt="`Banner ${i + 1}`"
                        class="w-full h-full object-contain" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                </a>
                <div v-else class="relative w-full h-full">
                    <img :src="b.imagen" :alt="`Banner ${i + 1}`"
                        class="w-full h-full object-contain" />
                </div>
            </div>
        </div>

        <!-- Arrows -->
        <template v-if="total > 1">
            <button @click="prev"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-surface/60 backdrop-blur-sm border border-border text-text-secondary hover:text-text hover:bg-surface/80 transition-all opacity-0 group-hover:opacity-100 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <button @click="next"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-surface/60 backdrop-blur-sm border border-border text-text-secondary hover:text-text hover:bg-surface/80 transition-all opacity-0 group-hover:opacity-100 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <!-- Dots -->
            <div class="absolute bottom-5 left-1/2 -translate-x-1/2 flex items-center gap-2">
                <button v-for="(_, i) in banners" :key="i" @click="goTo(i)"
                    class="transition-all duration-300 rounded-full"
                    :class="i === current ? 'w-6 h-2 bg-accent' : 'w-2 h-2 bg-white/30 hover:bg-white/50'" />
            </div>

            <!-- Counter -->
            <div class="absolute top-4 right-4 bg-surface/60 backdrop-blur-sm text-text-secondary text-[11px] px-2.5 py-1 rounded-lg border border-border">
                {{ current + 1 }} / {{ total }}
            </div>
        </template>
    </div>
</template>
