<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', negocio: '', tipo_cliente: 'particular', email: '', celular: '', direccion: '', ciudad: '', provincia: '', password: '', password_confirmation: '' });
const submit = () => { form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') }); };
</script>

<template>
    <GuestLayout>
        <Head title="Crear cuenta" />
        <div class="text-center mb-6">
            <h1 class="text-lg font-semibold text-text">Crear cuenta</h1>
            <p class="text-[13px] text-text-muted mt-1">Completá tus datos para empezar a comprar</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="flex gap-3">
                <label v-for="t in [{v:'particular',l:'Particular'},{v:'negocio',l:'Negocio / Dietética'}]" :key="t.v"
                    class="flex-1 border rounded-xl p-3 cursor-pointer text-center text-[13px] font-medium transition-all"
                    :class="form.tipo_cliente === t.v ? 'border-accent bg-accent/10 text-accent' : 'border-border text-text-secondary hover:border-border-hover'">
                    <input v-model="form.tipo_cliente" type="radio" :value="t.v" class="hidden" />{{ t.l }}
                </label>
            </div>

            <div>
                <label class="block text-[13px] text-text-secondary mb-1.5">Nombre completo *</label>
                <input v-model="form.name" type="text" required autofocus class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div v-if="form.tipo_cliente === 'negocio'">
                <label class="block text-[13px] text-text-secondary mb-1.5">Nombre del negocio</label>
                <input v-model="form.negocio" type="text" placeholder="Ej: Dietética Natural" class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text placeholder:text-text-muted focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] text-text-secondary mb-1.5">Email *</label>
                    <input v-model="form.email" type="email" required class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>
                <div>
                    <label class="block text-[13px] text-text-secondary mb-1.5">Celular *</label>
                    <input v-model="form.celular" type="tel" required placeholder="2477504048" class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text placeholder:text-text-muted focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
                    <InputError class="mt-1" :message="form.errors.celular" />
                </div>
            </div>

            <div>
                <label class="block text-[13px] text-text-secondary mb-1.5">Dirección *</label>
                <input v-model="form.direccion" type="text" required placeholder="Calle y número" class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text placeholder:text-text-muted focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] text-text-secondary mb-1.5">Ciudad *</label>
                    <input v-model="form.ciudad" type="text" required placeholder="Pergamino" class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text placeholder:text-text-muted focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
                </div>
                <div>
                    <label class="block text-[13px] text-text-secondary mb-1.5">Provincia</label>
                    <input v-model="form.provincia" type="text" placeholder="Buenos Aires" class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text placeholder:text-text-muted focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] text-text-secondary mb-1.5">Contraseña *</label>
                    <input v-model="form.password" type="password" required class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
                    <InputError class="mt-1" :message="form.errors.password" />
                </div>
                <div>
                    <label class="block text-[13px] text-text-secondary mb-1.5">Confirmar *</label>
                    <input v-model="form.password_confirmation" type="password" required class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition" />
                </div>
            </div>

            <button type="submit" :disabled="form.processing" class="w-full bg-accent hover:bg-accent-bright text-white font-medium py-3 rounded-xl transition-all disabled:opacity-50">
                {{ form.processing ? 'Creando cuenta...' : 'Crear cuenta' }}
            </button>
            <p class="text-center text-[13px] text-text-muted">¿Ya tenés cuenta? <Link :href="route('login')" class="text-accent hover:text-accent-bright transition">Ingresar</Link></p>
        </form>
    </GuestLayout>
</template>
