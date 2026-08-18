<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";

const props = defineProps({
    whatsappNumber: {
        type: Object,
        required: true,
    },

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
    meta_whatsapp_setting_id:
        props.whatsappNumber.meta_whatsapp_setting_id || "",

    phone_number_id: props.whatsappNumber.phone_number_id || "",

    waba_id: props.whatsappNumber.waba_id || "",

    business_account_id: props.whatsappNumber.business_account_id || "",

    phone_number: props.whatsappNumber.phone_number || "",

    display_phone_number: props.whatsappNumber.display_phone_number || "",

    verified_name: props.whatsappNumber.verified_name || "",

    access_token: "",

    change_access_token: false,

    is_active: Boolean(props.whatsappNumber.is_active),
});

function submit() {
    form.put(
        route(
            "superadmin.whatsapp-numbers.update",
            props.whatsappNumber.id,
        ),
        {
            onSuccess: () => {
                form.access_token = null;
                form.change_access_token = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Edit WhatsApp Number" />

    <SuperAdminLayout title="Edit WhatsApp Number">
        <div class="p-6 max-w-5xl">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <Link
                        :href="
                            route(
                                'superadmin.whatsapp-numbers.show',
                                whatsappNumber.id,
                            )
                        "
                        class="text-sm text-surface-500 hover:text-surface-700"
                    >
                        ← Back to WhatsApp Number
                    </Link>

                    <div class="flex items-center gap-3 mt-3">
                        <h1 class="text-xl font-semibold text-surface-900">
                            Edit WhatsApp Number
                        </h1>

                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                            :class="
                                whatsappNumber.is_active
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-surface-100 text-surface-600'
                            "
                        >
                            {{
                                whatsappNumber.is_active ? "Active" : "Inactive"
                            }}
                        </span>
                    </div>

                    <p class="mt-1 text-sm text-surface-500">
                        Update WhatsApp Cloud API configuration and assignment.
                    </p>
                </div>
            </div>

            <div
                v-if="Object.keys(form.errors).length"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3"
            >
                <div class="flex items-start gap-3">
                    <div class="shrink-0">
                        <svg
                            class="w-5 h-5 text-red-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v3.75m0 3.75h.008M10.29 3.86l-7.5 13A1.5 1.5 0 004.09 19h15.82a1.5 1.5 0 001.3-2.25l-7.5-13a1.5 1.5 0 00-2.6 0z"
                            />
                        </svg>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-red-800">
                            Please fix the following errors:
                        </p>

                        <ul class="mt-1 list-disc list-inside text-xs text-red-700">
                            <li
                                v-for="(error, field) in form.errors"
                                :key="field"
                            >
                                {{ error }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit">
                <!-- Meta Configuration -->
                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Meta WhatsApp Configuration
                        </h2>

                        <p class="text-xs text-surface-500 mt-1">
                            Select which Meta application configuration owns
                            this WhatsApp number.
                        </p>
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

                <!-- WhatsApp Details -->
                <div
                    class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            WhatsApp Business Number
                        </h2>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Phone Number ID -->
                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                Phone Number ID
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

                        <!-- Phone Number -->
                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                WhatsApp Phone Number
                            </label>

                            <input
                                v-model="form.phone_number"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />

                            <p
                                v-if="form.errors.phone_number"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.phone_number }}
                            </p>
                        </div>

                        <!-- Display Phone -->
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

                        <!-- Verified Name -->
                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                Verified Business Name
                            </label>

                            <input
                                v-model="form.verified_name"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg"
                            />
                        </div>

                        <!-- WABA -->
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

                        <!-- Business Account -->
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
                    </div>
                </div>

                <!-- Access Token -->
                <div
                    class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Access Token
                        </h2>

                        <p class="text-xs text-surface-500 mt-1">
                            The existing access token is hidden for security.
                        </p>
                    </div>

                    <div class="p-6">
                        <div
                            class="flex items-center justify-between gap-4 p-4 rounded-lg bg-surface-50 border border-surface-200"
                        >
                            <div class="min-w-0">
                                <p class="text-xs text-surface-500">
                                    Current Token
                                </p>

                                <p
                                    class="mt-1 text-sm font-mono text-surface-700 truncate"
                                >
                                    ••••••••••••••••••••••••••••••••
                                </p>
                            </div>

                            <label
                                class="inline-flex items-center gap-2 shrink-0 cursor-pointer"
                            >
                                <input
                                    v-model="form.change_access_token"
                                    type="checkbox"
                                    class="rounded border-surface-300 text-slate-600"
                                />

                                <span
                                    class="text-xs font-medium text-surface-700"
                                >
                                    Change token
                                </span>
                            </label>
                        </div>

                        <div v-if="form.change_access_token" class="mt-4">
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                New Access Token
                            </label>

                            <textarea
                                v-model="form.access_token"
                                rows="5"
                                placeholder="Paste the new Meta access token"
                                class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />

                            <p
                                v-if="form.errors.access_token"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.access_token }}
                            </p>

                            <p class="mt-1 text-xs text-surface-400">
                                Leave this disabled if the existing token should
                                remain unchanged.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Team Assignment -->
                <div
                    class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Team Assignment
                        </h2>

                        <p class="text-xs text-surface-500 mt-1">
                            Select which team will use this WhatsApp number.
                        </p>
                    </div>
                </div>

                <!-- Status -->
                <div
                    class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Status
                        </h2>
                    </div>

                    <div class="p-6">
                        <label
                            class="inline-flex items-center gap-3 cursor-pointer"
                        >
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-surface-300 text-slate-600"
                            />

                            <span>
                                <span
                                    class="block text-sm font-medium text-surface-900"
                                >
                                    Active
                                </span>

                                <span
                                    class="block text-xs text-surface-500 mt-0.5"
                                >
                                    Allow this number to be used for WhatsApp
                                    communication.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex justify-end gap-3">
                    <Link
                        :href="
                            route(
                                'superadmin.whatsapp-numbers.show',
                                whatsappNumber.id,
                            )
                        "
                        class="px-4 py-2 text-sm font-medium text-surface-700 bg-white border border-slate-500 rounded-lg hover:bg-surface-50 transition"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 transition disabled:opacity-50"
                    >
                        {{ form.processing ? "Saving..." : "Save Changes" }}
                    </button>
                </div>
            </form>
        </div>
    </SuperAdminLayout>
</template>