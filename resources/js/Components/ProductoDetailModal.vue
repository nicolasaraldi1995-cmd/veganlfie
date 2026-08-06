<script setup>
import { computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({ producto: { type: Object, default: null } });
const emit = defineEmits(['close']);

const presentaciones = computed(() => props.producto?.presentaciones ?? []);
const imagen = computed(() => props.producto?.imagen_url ?? presentaciones.value.find((p) => p.imagen_url)?.imagen_url ?? null);

// Además de la X y del clic afuera. Escape es lo que la gente aprieta primero
// para cerrar cualquier cosa que se abre encima.
//
// El escucha vive lo que vive esta ventana, que la tarjeta monta recién al
// abrirla: si estuviera siempre montada, la portada tendría 587 de estos
// colgados del documento.
function alTeclear(e) {
    if (e.key === 'Escape') emit('close');
}
onMounted(() => document.addEventListener('keydown', alTeclear));
onUnmounted(() => document.removeEventListener('keydown', alTeclear));
</script>

<template>
    <Teleport to="body">
        <!-- appear: como la tarjeta la monta al abrirla, sin esto la ventana
             aparecería de golpe en vez de fundirse. -->
        <Transition name="fade" appear>
            <div v-if="producto" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 backdrop-blur-sm p-4"
                role="dialog" aria-modal="true" @click.self="emit('close')">
                <div class="bg-surface-1 rounded-2xl border border-border w-full max-w-lg max-h-[85vh] overflow-y-auto">

                    <!-- object-contain y no cover: acá se viene a ver el producto
                         entero, no a que quede lindo el recuadro. -->
                    <div class="relative aspect-[5/4] bg-surface-2 rounded-t-2xl overflow-hidden shrink-0">
                        <img v-if="imagen" :src="imagen" :alt="producto.nombre" class="w-full h-full object-contain" />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <svg class="w-12 h-12 text-surface-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>

                        <button @click="emit('close')" aria-label="Cerrar"
                            class="absolute top-2.5 right-2.5 w-9 h-9 flex items-center justify-center rounded-full bg-black/50 text-white/90 hover:bg-black/70 hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>

                        <div class="absolute bottom-2.5 left-2.5 right-14 flex flex-wrap gap-1.5">
                            <span v-if="producto.nuevo" class="text-[12px] font-bold uppercase tracking-wide text-white bg-amber-500 px-2 py-1 rounded shadow-sm">Nuevo</span>
                            <span v-if="producto.sin_tacc" class="text-[12px] font-bold uppercase tracking-wide text-accent-dim bg-white/95 px-2 py-1 rounded shadow-sm">Sin TACC</span>
                            <span v-if="producto.frio" class="text-[12px] font-bold uppercase tracking-wide text-white bg-sky-500 px-2 py-1 rounded shadow-sm">Frío</span>
                            <span v-if="producto.congelado" class="text-[12px] font-bold uppercase tracking-wide text-white bg-blue-600 px-2 py-1 rounded shadow-sm">Congelado</span>
                        </div>
                    </div>

                    <div class="p-5">
                        <span v-if="producto.marca?.nombre" class="brand-label inline-block max-w-full truncate text-accent-dim bg-accent-muted px-2.5 py-1 rounded-full">{{ producto.marca.nombre }}</span>

                        <!-- Sin recortar: que el nombre entre entero es la razón
                             de ser de esta ventana. En la tarjeta se corta a dos
                             líneas y dos productos que sólo se diferencian en el
                             final del nombre se ven iguales. -->
                        <h3 class="text-[17px] font-bold text-text leading-snug mt-2">{{ producto.nombre }}</h3>

                        <p v-if="producto.categoria?.nombre" class="text-[13px] text-text-muted mt-1">{{ producto.categoria.nombre }}</p>

                        <p v-if="producto.descripcion" class="text-[14px] text-text-secondary leading-relaxed mt-4">{{ producto.descripcion }}</p>

                        <template v-if="presentaciones.length">
                            <h4 class="text-[12px] font-bold uppercase tracking-wide text-text-muted mt-5 mb-2">
                                {{ presentaciones.length === 1 ? 'Presentación' : 'Presentaciones' }}
                            </h4>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="p in presentaciones" :key="p.id"
                                    class="px-3 py-1.5 text-[13px] font-semibold text-text-secondary bg-surface-3 rounded-lg">{{ p.unidad }}</span>
                            </div>
                        </template>

                        <p class="text-[12px] text-text-muted mt-5">Cerrá esta ventana para elegir la presentación y agregarlo al carrito.</p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
@media (prefers-reduced-motion: reduce) {
    .fade-enter-active, .fade-leave-active { transition: none; }
}
</style>
