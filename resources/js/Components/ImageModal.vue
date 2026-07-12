<script setup>
import { ref, watch } from 'vue';
const props = defineProps({ src: { type: String, default: null } });
const emit = defineEmits(['close']);
const visible = ref(false);
watch(() => props.src, (val) => { visible.value = !!val; });
</script>
<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="visible && src" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 backdrop-blur-sm p-6" @click.self="emit('close')">
                <button @click="emit('close')" class="absolute top-5 right-5 text-white/60 hover:text-white transition">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <img :src="src" alt="" class="max-w-full max-h-[85vh] object-contain rounded-2xl" />
            </div>
        </Transition>
    </Teleport>
</template>
<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
