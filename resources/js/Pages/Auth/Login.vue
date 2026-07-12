<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ canResetPassword: Boolean, status: String });
const form = useForm({ email: '', password: '', remember: false });
const submit = () => { form.post(route('login'), { onFinish: () => form.reset('password') }); };
</script>
<template>
    <GuestLayout>
        <Head title="Ingresar" />
        <div class="text-center mb-6">
            <h1 class="text-lg font-semibold text-text">Ingresar</h1>
            <p class="text-[13px] text-text-muted mt-1">Accedé a tu cuenta VEGANLIFE</p>
        </div>
        <div v-if="status" class="mb-4 text-sm text-accent bg-accent/10 px-4 py-2 rounded-xl">{{ status }}</div>
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block text-[13px] text-text-secondary mb-1.5">Email</label>
                <input v-model="form.email" type="email" required autofocus class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text placeholder:text-text-muted focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>
            <div>
                <label class="block text-[13px] text-text-secondary mb-1.5">Contraseña</label>
                <input v-model="form.password" type="password" required class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text placeholder:text-text-muted focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-[13px] text-text-secondary cursor-pointer">
                    <input v-model="form.remember" type="checkbox" class="rounded bg-surface-2 border-border text-accent focus:ring-accent/20" /> Recordarme
                </label>
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-[12px] text-text-muted hover:text-accent transition">¿Olvidaste tu contraseña?</Link>
            </div>
            <button type="submit" :disabled="form.processing" class="w-full bg-accent hover:bg-accent-bright text-white font-medium py-3 rounded-xl transition-all disabled:opacity-50">
                {{ form.processing ? 'Ingresando...' : 'Ingresar' }}
            </button>
            <p class="text-center text-[13px] text-text-muted">¿No tenés cuenta? <Link :href="route('register')" class="text-accent hover:text-accent-bright transition">Crear cuenta</Link></p>
        </form>
    </GuestLayout>
</template>
