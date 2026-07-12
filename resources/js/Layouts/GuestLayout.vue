<script setup>
import { Link } from '@inertiajs/vue3';
</script>
<template>
    <div class="flex min-h-screen flex-col items-center bg-surface pt-10 sm:justify-center sm:pt-0 px-4">
        <div class="mb-6">
            <Link href="/">
                <img src="/images/logo.png" alt="VEGANLIFE" class="h-12 opacity-80" />
            </Link>
        </div>
        <div class="w-full overflow-hidden bg-surface-1 border border-border px-8 py-8 sm:max-w-lg sm:rounded-2xl">
            <slot />
        </div>
    </div>
</template>
