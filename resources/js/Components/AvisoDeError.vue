<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Cuando el servidor rechaza algo (sin stock, se pidió de más), el motivo
 * viajaba en los props pero ninguna pantalla lo mostraba: el cliente apretaba
 * "+" y no pasaba absolutamente nada. Va una sola vez en el marco de la página
 * y cubre el carrito, la ficha del producto y la edición del pedido, sin tener
 * que acordarse de engancharlo en cada botón.
 */
const page = usePage();
const visible = ref(false);
const mensaje = ref('');
let cerrarSolo = null;

const primerError = computed(() => {
    const errores = page.props.errors ?? {};
    const claves = Object.keys(errores);

    return claves.length ? errores[claves[0]] : null;
});

watch(primerError, (texto) => {
    if (!texto) return;

    mensaje.value = texto;
    visible.value = true;

    clearTimeout(cerrarSolo);
    cerrarSolo = setTimeout(() => { visible.value = false; }, 5000);
}, { immediate: true });
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-3"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0 translate-y-3">
        <div v-if="visible" role="alert" aria-live="polite"
            class="fixed inset-x-3 bottom-4 z-50 mx-auto max-w-md sm:inset-x-auto sm:right-4">
            <div class="flex items-start gap-3 rounded-xl border border-red-500/30 bg-red-500/95 px-4 py-3 shadow-lg backdrop-blur-sm">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="flex-1 text-sm font-medium leading-snug text-white">{{ mensaje }}</p>
                <button @click="visible = false" aria-label="Cerrar aviso"
                    class="shrink-0 text-white/70 transition hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </Transition>
</template>
