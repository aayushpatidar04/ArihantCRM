<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";

const form = useForm({
    name: "",
    app_id: "",
    app_secret: "",
    verify_token: "",
    webhook_url: "",
    is_active: true,
});

function submit() {
    form.post(route("superadmin.meta-whatsapp-settings.store"));
}
</script>

<template>
    <Head title="Add Meta WhatsApp App" />

    <SuperAdminLayout title="Add Meta WhatsApp App">
        <div class="p-6 max-w-4xl">
            <div class="mb-6">
                <Link
                    :href="route('superadmin.meta-whatsapp-settings.index')"
                    class="text-sm text-slate-600 hover:text-slate-700"
                >
                    ← Back to Meta Apps
                </Link>

                <h1 class="mt-3 text-xl font-semibold text-surface-900">
                    Add Meta WhatsApp App
                </h1>

                <p class="mt-1 text-sm text-surface-500">
                    Configure a Meta application that can be used by one or more
                    WhatsApp numbers.
                </p>
            </div>

            <form
                @submit.prevent="submit"
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="p-6 space-y-6">
                    <!-- Name -->
                    <div>
                        <label class="form-label"> Configuration Name </label>

                        <input
                            v-model="form.name"
                            type="text"
                            class="form-input"
                            placeholder="Main WhatsApp App"
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- App ID -->
                    <div>
                        <label class="form-label"> Meta App ID </label>

                        <input
                            v-model="form.app_id"
                            type="text"
                            class="form-input"
                            placeholder="Meta App ID"
                        />

                        <p v-if="form.errors.app_id" class="form-error">
                            {{ form.errors.app_id }}
                        </p>
                    </div>

                    <!-- App Secret -->
                    <div>
                        <label class="form-label"> App Secret </label>

                        <input
                            v-model="form.app_secret"
                            type="password"
                            class="form-input"
                            autocomplete="new-password"
                        />

                        <p v-if="form.errors.app_secret" class="form-error">
                            {{ form.errors.app_secret }}
                        </p>
                    </div>

                    <!-- Verify Token -->
                    <div>
                        <label class="form-label"> Webhook Verify Token </label>

                        <input
                            v-model="form.verify_token"
                            type="text"
                            class="form-input"
                        />

                        <p v-if="form.errors.verify_token" class="form-error">
                            {{ form.errors.verify_token }}
                        </p>
                    </div>

                    <!-- Webhook -->
                    <div>
                        <label class="form-label"> Webhook URL </label>

                        <input
                            v-model="form.webhook_url"
                            type="url"
                            class="form-input"
                            placeholder="https://example.com/webhooks/meta/whatsapp"
                        />

                        <p v-if="form.errors.webhook_url" class="form-error">
                            {{ form.errors.webhook_url }}
                        </p>
                    </div>

                    <!-- Active -->
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-surface-300 text-slate-600"
                        />

                        <span class="text-sm text-surface-700"> Active </span>
                    </label>
                </div>

                <div
                    class="px-6 py-4 border-t border-surface-200 flex justify-end gap-3"
                >
                    <Link
                        :href="route('superadmin.meta-whatsapp-settings.index')"
                        class="px-4 py-2 text-sm font-medium text-surface-700 bg-white border border-slate-500 rounded-lg"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-lg disabled:opacity-50"
                    >
                        {{ form.processing ? "Saving..." : "Save Meta App" }}
                    </button>
                </div>
            </form>
        </div>
    </SuperAdminLayout>
</template>

<style scoped>
.form-label {
    @apply block text-sm font-medium text-surface-700 mb-1.5;
}

.form-input {
    @apply w-full rounded-lg border border-surface-200 px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-500;
}

.form-error {
    @apply mt-1 text-xs text-red-600;
}
</style>
