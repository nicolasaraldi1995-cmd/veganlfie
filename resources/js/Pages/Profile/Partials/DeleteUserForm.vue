<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-4">
        <header>
            <h2 class="text-[15px] font-semibold text-text">Eliminar cuenta</h2>
            <p class="text-[13px] text-text-muted mt-1 leading-relaxed">
                Una vez que elimines tu cuenta, todos tus datos y pedidos se borrarán de forma permanente.
            </p>
        </header>

        <button
            @click="confirmUserDeletion"
            class="bg-red-500/10 hover:bg-red-500/15 text-red-500 font-medium text-[13px] px-5 py-2.5 rounded-xl transition-all"
        >
            Eliminar cuenta
        </button>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-[15px] font-semibold text-text">
                    ¿Estás seguro que querés eliminar tu cuenta?
                </h2>

                <p class="mt-1 text-[13px] text-text-muted leading-relaxed">
                    Una vez eliminada, todos tus datos y pedidos se borrarán de forma permanente. Ingresá tu contraseña para confirmar.
                </p>

                <div class="mt-4">
                    <label for="password_delete" class="sr-only">Contraseña</label>
                    <input
                        id="password_delete"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        placeholder="Contraseña"
                        class="w-3/4 bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text focus:border-accent focus:ring-1 focus:ring-accent/20 transition"
                        @keyup.enter="deleteUser"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="closeModal"
                        class="bg-surface-3 hover:bg-surface-4 text-text-secondary font-medium text-[13px] px-5 py-2.5 rounded-xl transition-all"
                    >
                        Cancelar
                    </button>

                    <button
                        @click="deleteUser"
                        :disabled="form.processing"
                        class="bg-red-500 hover:bg-red-600 text-white font-medium text-[13px] px-5 py-2.5 rounded-xl transition-all disabled:opacity-50"
                    >
                        Eliminar cuenta
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
