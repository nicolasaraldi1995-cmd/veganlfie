<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header class="mb-5">
            <h2 class="text-[15px] font-semibold text-text">Cambiar contraseña</h2>
            <p class="text-[13px] text-text-muted mt-1">Usá una contraseña larga y única para mantener tu cuenta segura.</p>
        </header>

        <form @submit.prevent="updatePassword" class="space-y-4">
            <div>
                <label for="current_password" class="block text-[13px] text-text-secondary mb-1.5">Contraseña actual</label>
                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    autocomplete="current-password"
                    class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition"
                />
                <InputError class="mt-1" :message="form.errors.current_password" />
            </div>

            <div>
                <label for="password" class="block text-[13px] text-text-secondary mb-1.5">Nueva contraseña</label>
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition"
                />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-[13px] text-text-secondary mb-1.5">Confirmar nueva contraseña</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition"
                />
                <InputError class="mt-1" :message="form.errors.password_confirmation" />
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
