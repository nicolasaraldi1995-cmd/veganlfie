<script setup>
defineProps({ title: String, linkHref: { type: String, default: null }, linkText: { type: String, default: 'Ver todos' } });
</script>
<template>
    <section>
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-text">{{ title }}</h2>
            <a v-if="linkHref" :href="linkHref" class="text-accent text-[13px] hover:text-accent-bright transition">{{ linkText }} →</a>
        </div>
        <slot />
    </section>
</template>
