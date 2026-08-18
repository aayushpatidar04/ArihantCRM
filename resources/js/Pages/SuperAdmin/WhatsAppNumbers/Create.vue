<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";

const props = defineProps({
    metaSettings: {
        type: Array,
        default: () => [],
    },
    teams: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    meta_whatsapp_setting_id: "",
    phone_number_id: "",
    waba_id: "",
    business_account_id: "",
    phone_number: "",
    display_phone_number: "",
    verified_name: "",
    access_token: "",
    is_active: true,
});

function submit() {
    form.post(route("superadmin.whatsapp-numbers.store"));
}
</script>

<template>
    <Head title="Add WhatsApp Number" />

    <SuperAdminLayout title="Add WhatsApp Number">
        <div class="p-6 max-w-4xl">
            <div class="mb-6">
                <Link
                    :href="route('superadmin.whatsapp-numbers.index')"
                    class="text-sm text-surface-500 hover:text-surface-700"
                >
                    ← Back to WhatsApp Numbers
                </Link>

                <h1 class="mt-3 text-xl font-semibold text-surface-900">
                    Add WhatsApp Number
                </h1>

                <p class="mt-1 text-sm text-surface-500">
                    Connect a WhatsApp Cloud API number to a Meta configuration.
                </p>
            </div>

            <form @submit.prevent="submit">
                <!-- Meta configuration -->
                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Meta WhatsApp Configuration
                        </h2>
                    </div>

                    <div class="p-6">
                        <label
                            class="block text-xs font-medium text-surface-700 mb-2"
                        >
                            Meta Configuration
                        </label>

                        <select
                            v-model="form.meta_whatsapp_setting_id"
                            class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Select Meta configuration</option>

                            <option
                                v-for="setting in metaSettings"
                                :key="setting.id"
                                :value="setting.id"
                            >
                                {{ setting.name }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.meta_whatsapp_setting_id"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.meta_whatsapp_setting_id }}
                        </p>
                    </div>
                </div>

                <!-- WhatsApp details -->
                <div
                    class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            WhatsApp Business Number
                        </h2>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                Phone Number ID *
                            </label>

                            <input
                                v-model="form.phone_number_id"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />

                            <p
                                v-if="form.errors.phone_number_id"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.phone_number_id }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                WhatsApp Phone Number *
                            </label>

                            <input
                                v-model="form.phone_number"
                                type="text"
                                placeholder="+919876543210"
                                class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />

                            <p
                                v-if="form.errors.phone_number"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.phone_number }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                Display Phone Number
                            </label>

                            <input
                                v-model="form.display_phone_number"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                Verified Name
                            </label>

                            <input
                                v-model="form.verified_name"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                WABA ID
                            </label>

                            <input
                                v-model="form.waba_id"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                Business Account ID
                            </label>

                            <input
                                v-model="form.business_account_id"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                Access Token *
                            </label>

                            <textarea
                                v-model="form.access_token"
                                rows="4"
                                class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />

                            <p
                                v-if="form.errors.access_token"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.access_token }}
                            </p>

                            <p class="mt-1 text-xs text-surface-400">
                                The token is stored securely and is never
                                displayed in full.
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="inline-flex items-center gap-2">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-surface-300 text-slate-600"
                                />

                                <span class="text-sm text-surface-700">
                                    Active
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex justify-end gap-3">
                    <Link
                        :href="route('superadmin.whatsapp-numbers.index')"
                        class="px-4 py-2 text-sm font-medium text-surface-700 bg-white border border-slate-500 rounded-lg hover:bg-surface-50"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? "Saving..."
                                : "Add WhatsApp Number"
                        }}
                    </button>
                </div>
            </form>
        </div>
    </SuperAdminLayout>
</template>