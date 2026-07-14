<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Mi cuenta" />

    <PublicLayout>
        <div class="max-w-xl mx-auto px-6 py-8 space-y-6">
            <h1 class="text-xl font-semibold text-text mb-2">Mi cuenta</h1>

            <div class="bg-surface-1 rounded-2xl border border-border p-6">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </div>

            <div class="bg-surface-1 rounded-2xl border border-border p-6">
                <UpdatePasswordForm />
            </div>

            <div class="bg-surface-1 rounded-2xl border border-border p-6">
                <DeleteUserForm />
            </div>
        </div>
    </PublicLayout>
</template>
