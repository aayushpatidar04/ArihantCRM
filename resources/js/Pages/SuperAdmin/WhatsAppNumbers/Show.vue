<script setup>
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";
import { useToast } from "@/Composables/useToast";
import { watch } from "vue";

const props = defineProps({
    whatsappNumber: {
        type: Object,
        required: true,
    },
});

const toast = useToast();

function formatDate(dateString) {
    if (!dateString) return "—";

    return new Intl.DateTimeFormat("en-IN", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(dateString));
}

function toggleStatus() {
    router.patch(
        route("superadmin.whatsapp-numbers.toggle", props.whatsappNumber.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Status updated.");
            },
            onError: () => toast.error("Please try again."),
        },
    );
}

function deleteNumber() {
    const number =
        props.whatsappNumber.display_phone_number ||
        props.whatsappNumber.phone_number;

    if (!confirm(`Are you sure you want to delete ${number}?`)) {
        return;
    }

    router.delete(
        route("superadmin.whatsapp-numbers.destroy", props.whatsappNumber.id),
        {},
        {
            onSuccess: () => {
                toast.success("Number deleted.");
            },
            onError: () => toast.error("Please try again."),
        },
    );
}
</script>

<template>
    <Head
        :title="
            whatsappNumber.display_phone_number || whatsappNumber.phone_number
        "
    />

    <SuperAdminLayout
        :title="
            whatsappNumber.display_phone_number || whatsappNumber.phone_number
        "
    >
        <div class="p-6">
            <!-- Header -->
            <div
                class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-6"
            >
                <div>
                    <Link
                        :href="route('superadmin.whatsapp-numbers.index')"
                        class="text-sm text-surface-500 hover:text-surface-700"
                    >
                        ← Back to WhatsApp Numbers
                    </Link>

                    <div class="flex items-center gap-3 mt-3">
                        <h1 class="text-xl font-semibold text-surface-900">
                            {{
                                whatsappNumber.display_phone_number ||
                                whatsappNumber.phone_number
                            }}
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

                    <p
                        v-if="whatsappNumber.verified_name"
                        class="mt-1 text-sm text-surface-500"
                    >
                        {{ whatsappNumber.verified_name }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="toggleStatus"
                        class="px-4 py-2 text-sm font-medium rounded-lg border transition"
                        :class="
                            whatsappNumber.is_active
                                ? 'text-amber-700 bg-amber-50 border-amber-200 hover:bg-amber-100'
                                : 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100'
                        "
                    >
                        {{
                            whatsappNumber.is_active ? "Deactivate" : "Activate"
                        }}
                    </button>

                    <Link
                        :href="
                            route(
                                'superadmin.whatsapp-numbers.edit',
                                whatsappNumber.id,
                            )
                        "
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 transition"
                    >
                        Edit
                    </Link>

                    <button
                        type="button"
                        @click="deleteNumber"
                        class="px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition"
                    >
                        Delete
                    </button>

                    <Link
                        :href="
                            route(
                                'superadmin.whatsapp-numbers.test-connection',
                                whatsappNumber.id,
                            )
                        "
                        method="post"
                        as="button"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9M4.582 9H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2M19.419 15H15"
                            />
                        </svg>

                        Test Connection
                    </Link>

                    <Link
                        :href="
                            route(
                                'superadmin.whatsapp-numbers.sync-templates',
                                whatsappNumber.id,
                            )
                        "
                        method="post"
                        as="button"
                        preserve-scroll
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 transition"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h5M20 20v-5h-5M5.64 9A7 7 0 0117.9 6.1L20 9M18.36 15A7 7 0 016.1 17.9L4 15"
                            />
                        </svg>

                        Sync Templates
                    </Link>
                </div>
            </div>

            <!-- Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Connection -->
                <div
                    class="lg:col-span-2 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            WhatsApp Configuration
                        </h2>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Phone -->
                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Phone Number
                            </p>

                            <p class="text-sm font-medium text-surface-900">
                                {{
                                    whatsappNumber.display_phone_number ||
                                    whatsappNumber.phone_number ||
                                    "—"
                                }}
                            </p>
                        </div>

                        <!-- Verified Name -->
                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Verified Business Name
                            </p>

                            <p class="text-sm font-medium text-surface-900">
                                {{ whatsappNumber.verified_name || "—" }}
                            </p>
                        </div>

                        <!-- Phone Number ID -->
                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Phone Number ID
                            </p>

                            <p
                                class="text-sm font-medium text-surface-900 break-all"
                            >
                                {{ whatsappNumber.phone_number_id || "—" }}
                            </p>
                        </div>

                        <!-- WABA -->
                        <div>
                            <p class="text-xs text-surface-500 mb-1">WABA ID</p>

                            <p
                                class="text-sm font-medium text-surface-900 break-all"
                            >
                                {{ whatsappNumber.waba_id || "—" }}
                            </p>
                        </div>

                        <!-- Business Account -->
                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Business Account ID
                            </p>

                            <p
                                class="text-sm font-medium text-surface-900 break-all"
                            >
                                {{ whatsappNumber.business_account_id || "—" }}
                            </p>
                        </div>

                        <!-- Number ID -->
                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Internal Number ID
                            </p>

                            <p class="text-sm font-medium text-surface-900">
                                #{{ whatsappNumber.id }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm h-fit"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Connection Status
                        </h2>
                    </div>

                    <div class="p-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-full flex items-center justify-center"
                                :class="
                                    whatsappNumber.is_active
                                        ? 'bg-emerald-50'
                                        : 'bg-surface-100'
                                "
                            >
                                <svg
                                    v-if="whatsappNumber.is_active"
                                    class="w-5 h-5 text-emerald-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>

                                <svg
                                    v-else
                                    class="w-5 h-5 text-surface-500"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-surface-900">
                                    {{
                                        whatsappNumber.is_active
                                            ? "Active"
                                            : "Inactive"
                                    }}
                                </p>

                                <p class="text-xs text-surface-500 mt-0.5">
                                    {{
                                        whatsappNumber.is_active
                                            ? "Number is enabled."
                                            : "Number is disabled."
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Meta Configuration -->
            <div
                class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-surface-900">
                                Meta WhatsApp Configuration
                            </h2>

                            <p class="text-xs text-surface-500 mt-1">
                                Meta application configuration associated with
                                this number.
                            </p>
                        </div>

                        <Link
                            v-if="whatsappNumber.meta_whatsapp_setting"
                            :href="
                                route(
                                    'superadmin.meta-whatsapp-settings.show',
                                    whatsappNumber.meta_whatsapp_setting.id,
                                )
                            "
                            class="text-xs font-medium text-slate-600 hover:text-slate-700"
                        >
                            View Configuration →
                        </Link>
                    </div>
                </div>

                <div class="p-6">
                    <template v-if="whatsappNumber.meta_whatsapp_setting">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <p class="text-xs text-surface-500 mb-1">
                                    Configuration Name
                                </p>

                                <p class="text-sm font-medium text-surface-900">
                                    {{
                                        whatsappNumber.meta_whatsapp_setting
                                            .name
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-surface-500 mb-1">
                                    Configuration App ID
                                </p>

                                <p class="text-sm font-medium text-surface-900">
                                    {{
                                        whatsappNumber.meta_whatsapp_setting
                                            .app_id
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-surface-500 mb-1">
                                    Status
                                </p>

                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                                    :class="
                                        whatsappNumber.meta_whatsapp_setting
                                            .is_active
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-surface-100 text-surface-600'
                                    "
                                >
                                    {{
                                        whatsappNumber.meta_whatsapp_setting
                                            .is_active
                                            ? "Active"
                                            : "Inactive"
                                    }}
                                </span>
                            </div>
                        </div>
                    </template>

                    <div
                        v-else
                        class="flex items-center gap-3 p-4 rounded-lg bg-amber-50 border border-amber-100"
                    >
                        <svg
                            class="w-5 h-5 text-amber-600 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86l-7.5 13A2 2 0 004.52 20h14.96a2 2 0 001.73-3.14l-7.5-13a2 2 0 00-3.42 0z"
                            />
                        </svg>

                        <div>
                            <p class="text-sm font-medium text-amber-800">
                                No Meta configuration assigned
                            </p>

                            <p class="text-xs text-amber-700 mt-0.5">
                                Assign a Meta WhatsApp configuration before
                                using this number.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Assignment -->
            <div
                class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Assigned Teams
                    </h2>
                </div>

                <div class="p-6">
                    <template
                        v-if="
                            whatsappNumber.teams &&
                            whatsappNumber.teams.length > 0
                        "
                    >
                        <div
                            v-for="team in whatsappNumber.teams"
                            :key="team.id"
                            class="flex items-center justify-between mb-4 last:mb-0"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-lg bg-slate-50 flex items-center justify-center"
                                >
                                    <svg
                                        class="w-5 h-5 text-slate-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8zm6 4a4 4 0 00-4-4"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <Link
                                        :href="
                                            route(
                                                'superadmin.teams.show',
                                                team.id,
                                            )
                                        "
                                        class="text-sm font-medium text-slate-600 hover:text-slate-700"
                                    >
                                        {{ team.name }}
                                    </Link>
                                    <p class="text-xs text-surface-500 mt-0.5">
                                        Team ID: #{{ team.id }}
                                    </p>
                                </div>
                            </div>

                            <Link
                                :href="route('superadmin.teams.show', team.id)"
                                class="px-3 py-1.5 text-xs font-medium text-surface-700 bg-surface-100 rounded-lg hover:bg-surface-200"
                            >
                                View Team
                            </Link>
                        </div>
                    </template>

                    <div
                        v-else
                        class="flex items-center gap-3 p-4 rounded-lg bg-surface-50 border border-surface-200"
                    >
                        <svg
                            class="w-5 h-5 text-surface-400 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v3m0 4h.01M5.07 19h13.86a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16a2 2 0 001.73 3z"
                            />
                        </svg>

                        <div>
                            <p class="text-sm font-medium text-surface-700">
                                No team assigned
                            </p>
                            <p class="text-xs text-surface-500 mt-0.5">
                                This number is currently available for
                                assignment.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity / Webhook -->
            <div
                class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Connection Activity
                    </h2>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Last Connected
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ formatDate(whatsappNumber.last_connected_at) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Last Webhook
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ formatDate(whatsappNumber.last_webhook_at) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">Created</p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ formatDate(whatsappNumber.created_at) }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Meta Connection
                    </h2>
                </div>

                <div
                    class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"
                >
                    <div>
                        <p class="text-xs text-surface-500 mb-1">API Status</p>

                        <span
                            v-if="whatsappNumber.last_connected_at"
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700"
                        >
                            Connected
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700"
                        >
                            Not Tested
                        </span>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Quality Rating
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ whatsappNumber.quality_rating || "—" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Verification Status
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ whatsappNumber.code_verification_status || "—" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Last Connection Check
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{
                                formatDate(
                                    whatsappNumber.last_connection_check_at,
                                ) || "Never"
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="whatsappNumber.last_connection_error"
                    class="mx-6 mb-6 p-3 rounded-lg bg-red-50 border border-red-100"
                >
                    <p class="text-xs font-medium text-red-700 mb-1">
                        Last Connection Error
                    </p>

                    <p class="text-xs text-red-600">
                        {{ whatsappNumber.last_connection_error }}
                    </p>
                </div>
            </div>

            <!-- Security -->
            <div
                class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Security
                    </h2>
                </div>

                <div class="p-6">
                    <div
                        class="flex items-center gap-3 p-4 rounded-lg bg-surface-50 border border-surface-200"
                    >
                        <div
                            class="w-9 h-9 rounded-full bg-emerald-50 flex items-center justify-center"
                        >
                            <svg
                                class="w-5 h-5 text-emerald-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 15v2m-6 3h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v3h8z"
                                />
                            </svg>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-surface-900">
                                Access token protected
                            </p>

                            <p class="text-xs text-surface-500 mt-0.5">
                                The WhatsApp Cloud API access token is never
                                displayed on this page.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
