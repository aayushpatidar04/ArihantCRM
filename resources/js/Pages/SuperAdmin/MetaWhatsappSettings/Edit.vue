<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";

const props = defineProps({
    setting: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.setting.name,
    app_id: props.setting.app_id,
    app_secret: "",
    verify_token: "",
    webhook_url: props.setting.webhook_url ?? "",
    is_active: props.setting.is_active,
});

function submit() {
    form.put(
        route("superadmin.meta-whatsapp-settings.update", props.setting.id),
    );
}
</script>

<template>
    <Head title="Edit Meta WhatsApp App" />

    <SuperAdminLayout title="Edit Meta WhatsApp App">
        <div class="p-6 max-w-4xl">
            <div class="mb-6">
                <Link
                    :href="route('superadmin.meta-whatsapp-settings.index')"
                    class="text-sm text-slate-600"
                >
                    ← Back to Meta Apps
                </Link>

                <h1 class="mt-3 text-xl font-semibold text-surface-900">
                    Edit Meta WhatsApp App
                </h1>
            </div>

            <form
                @submit.prevent="submit"
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="p-6 space-y-6">
                    <div>
                        <label class="form-label"> Configuration Name </label>

                        <input
                            v-model="form.name"
                            type="text"
                            class="form-input"
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="form-label"> Meta App ID </label>

                        <input
                            v-model="form.app_id"
                            type="text"
                            class="form-input"
                        />

                        <p v-if="form.errors.app_id" class="form-error">
                            {{ form.errors.app_id }}
                        </p>
                    </div>

                    <div>
                        <label class="form-label"> App Secret </label>

                        <input
                            v-model="form.app_secret"
                            type="password"
                            class="form-input"
                            placeholder="Leave blank to keep current secret"
                        />
                    </div>

                    <div>
                        <label class="form-label"> Webhook Verify Token </label>

                        <input
                            v-model="form.verify_token"
                            type="text"
                            class="form-input"
                            placeholder="Leave blank to keep current token"
                        />
                    </div>

                    <div>
                        <label class="form-label"> Webhook URL </label>

                        <input
                            v-model="form.webhook_url"
                            type="url"
                            class="form-input"
                        />
                    </div>

                    <label class="flex items-center gap-3">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-surface-300 text-slate-600"
                        />

                        <span class="text-sm"> Active </span>
                    </label>

                    <div
                        class="rounded-lg bg-surface-50 border border-surface-200 px-4 py-3"
                    >
                        <p class="text-xs text-surface-500">
                            Connected WhatsApp Numbers
                        </p>

                        <p class="mt-1 text-sm font-semibold text-surface-900">
                            {{ setting.whatsapp_numbers_count }}
                        </p>
                    </div>
                </div>

                <div
                    class="px-6 py-4 border-t border-surface-200 flex justify-end gap-3"
                >
                    <Link
                        :href="route('superadmin.meta-whatsapp-settings.index')"
                        class="px-4 py-2 text-sm border border-surface-200 rounded-lg"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg disabled:opacity-50"
                    >
                        {{ form.processing ? "Updating..." : "Update App" }}
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
