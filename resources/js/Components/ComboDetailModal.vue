<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ combo: { type: Object, default: null } });
const emit = defineEmits(['close']);

const items = computed(() => props.combo?.items ?? []);

function addCombo() {
    if (!props.combo) return;
    router.post(route('cart.add-combo'), { combo_id: props.combo.id }, { preserveScroll: true });
    emit('close');
}
</script>
<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="combo" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" @click.self="emit('close')">
                <div class="bg-surface-1 rounded-2xl border border-border w-full max-w-lg max-h-[85vh] overflow-y-auto">
                    <div class="relative aspect-video bg-surface-3 rounded-t-2xl overflow-hidden shrink-0">
                        <img v-if="combo.imagen_url" :src="combo.imagen_url" class="w-full h-full object-cover" />
                        <span class="absolute top-2.5 left-2.5 bg-purple-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-lg">COMBO</span>
                        <button @click="emit('close')" class="absolute top-2.5 right-2.5 w-8 h-8 flex items-center justify-center rounded-full bg-black/50 text-white/90 hover:bg-black/70 hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-5">
                        <h3 class="text-lg font-semibold text-text">{{ combo.nombre }}</h3>
                        <p v-if="combo.descripcion" class="text-sm text-text-muted mt-1">{{ combo.descripcion }}</p>

                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-text-muted mt-5 mb-2">Incluye</h4>
                        <div class="space-y-2">
                            <div v-for="item in items" :key="item.id" class="flex items-center gap-3 bg-surface-2 rounded-xl p-2.5">
                                <div class="w-12 h-12 rounded-lg bg-surface-3 overflow-hidden shrink-0">
                                    <img v-if="item.presentacion?.imagen_url || item.presentacion?.producto?.imagen_url"
                                        :src="item.presentacion?.imagen_url || item.presentacion?.producto?.imagen_url"
                                        class="w-full h-full object-cover" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span v-if="item.presentacion?.producto?.marca?.nombre" class="brand-label inline-block max-w-full truncate text-accent-dim bg-accent-muted px-2 py-0.5 rounded-full text-[10px]">{{ item.presentacion.producto.marca.nombre }}</span>
                                    <p class="text-[13.5px] font-medium text-text leading-tight truncate mt-0.5">{{ item.presentacion?.producto?.nombre }}</p>
                                    <p class="text-[11px] text-text-muted mt-0.5">{{ item.presentacion?.unidad }}</p>
                                </div>
                                <span class="text-[13px] font-bold text-text-secondary shrink-0">x{{ item.cantidad }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-5 pt-4 border-t border-border">
                            <div>
                                <del v-if="combo.descuento_porcentaje && combo.precio_sin_descuento !== combo.precio_final" class="text-[12px] text-text-muted">${{ Math.round(combo.precio_sin_descuento).toLocaleString('es-AR') }}</del>
                                <span v-if="combo.descuento_porcentaje" class="text-[11px] text-red-400 ml-1">-{{ combo.descuento_porcentaje }}%</span>
                                <p class="text-xl font-semibold text-text">${{ Math.round(combo.precio_final).toLocaleString('es-AR') }}</p>
                            </div>
                            <button @click="addCombo" class="bg-accent hover:bg-accent-bright text-white text-[13px] font-semibold px-5 py-2.5 rounded-lg transition-all active:scale-[0.98]">
                                Agregar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
