<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({ links: Array });

/**
 * Laravel manda la primera y la última como "Previous"/"Next" en inglés (o
 * como la clave sin traducir, según el idioma configurado). Se reemplazan por
 * flechas: se entienden en cualquier idioma y ocupan menos en el celular.
 */
function etiqueta(indice, link) {
    if (indice === 0) return '‹';
    if (indice === props.links.length - 1) return '›';

    return link.label;
}
</script>
<template>
    <!-- flex-wrap: con muchas páginas la fila medía más que la pantalla del
         celular y arrastraba toda la página para el costado. -->
    <nav v-if="links.length > 3" class="flex flex-wrap justify-center gap-1 mt-10">
        <template v-for="(link, i) in links" :key="i">
            <Link v-if="link.url" :href="link.url"
                :aria-label="i === 0 ? 'Página anterior' : (i === links.length - 1 ? 'Página siguiente' : 'Página ' + link.label)"
                class="px-3 py-2 text-[13px] rounded-lg transition-all"
                :class="link.active ? 'bg-accent text-white font-medium' : 'bg-surface-2 text-text-secondary hover:bg-surface-3 hover:text-text border border-border'"
                v-html="etiqueta(i, link)" />
            <span v-else class="px-3 py-2 text-[13px] text-text-muted" v-html="etiqueta(i, link)" />
        </template>
    </nav>
</template>
