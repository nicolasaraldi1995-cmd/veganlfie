<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ links: Array });
</script>
<template>
    <nav v-if="links.length > 3" class="flex justify-center gap-1 mt-10">
        <template v-for="link in links" :key="link.label">
            <Link v-if="link.url" :href="link.url"
                class="px-3 py-2 text-[13px] rounded-lg transition-all"
                :class="link.active ? 'bg-accent text-white font-medium' : 'bg-surface-2 text-text-secondary hover:bg-surface-3 hover:text-text border border-border'"
                v-html="link.label" />
            <span v-else class="px-3 py-2 text-[13px] text-text-muted" v-html="link.label" />
        </template>
    </nav>
</template>
