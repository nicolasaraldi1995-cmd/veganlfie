<script setup>
import InputError from '@/Components/InputError.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header class="mb-5">
            <h2 class="text-[15px] font-semibold text-text">Información de perfil</h2>
            <p class="text-[13px] text-text-muted mt-1">Actualizá tu nombre y tu email de la cuenta.</p>
        </header>

        <form @submit.prevent="form.patch(route('profile.update'))" class="space-y-4">
            <div>
                <label for="name" class="block text-[13px] text-text-secondary mb-1.5">Nombre</label>
                <input
                    id="name"
                    type="text"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition"
                />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div>
                <label for="email" class="block text-[13px] text-text-secondary mb-1.5">Email</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition"
                />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="text-[13px] text-text-secondary leading-relaxed">
                    Tu dirección de email no está verificada.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="text-accent hover:text-accent-bright underline transition"
                    >
                        Reenviar email de verificación.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-[13px] font-medium text-emerald-600"
                >
                    Se envió un nuevo link de verificación a tu email.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-accent hover:bg-accent-bright text-white font-medium text-[13px] px-5 py-2.5 rounded-xl transition-all disabled:opacity-50"
                >
                    Guardar
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-[13px] text-text-muted">
                        Guardado.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
