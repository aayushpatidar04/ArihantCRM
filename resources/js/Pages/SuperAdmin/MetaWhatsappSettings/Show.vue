<script setup>
import { Head, Link } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    metaWhatsappSetting: {
        type: Object,
        required: true,
    },
});

const { success, error, info } = useToast();

function formatDate(dateString) {
    if (!dateString) return "—";

    return new Intl.DateTimeFormat("en-IN", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(dateString));
}
</script>

<template>
    <Head :title="metaWhatsappSetting.name" />

    <SuperAdminLayout :title="metaWhatsappSetting.name">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold text-surface-900">
                            {{ metaWhatsappSetting.name }}
                        </h1>

                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                            :class="
                                metaWhatsappSetting.is_active
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-surface-100 text-surface-600'
                            "
                        >
                            {{
                                metaWhatsappSetting.is_active
                                    ? "Active"
                                    : "Inactive"
                            }}
                        </span>
                    </div>

                    <p class="mt-1 text-sm text-surface-500">
                        Meta WhatsApp application configuration.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('superadmin.meta-whatsapp-settings.index')"
                        class="px-4 py-2 text-sm font-medium text-surface-700 bg-white border border-surface-200 rounded-lg hover:bg-surface-50 transition"
                    >
                        ← Back
                    </Link>

                    <Link
                        :href="
                            route(
                                'superadmin.meta-whatsapp-settings.edit',
                                metaWhatsappSetting.id,
                            )
                        "
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 transition"
                    >
                        Edit Configuration
                    </Link>
                </div>
            </div>

            <!-- Configuration -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Meta Configuration
                    </h2>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Configuration Name
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ metaWhatsappSetting.name || "—" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">App ID</p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ metaWhatsappSetting.app_id || "—" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Business Portfolio ID
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{
                                metaWhatsappSetting.business_portfolio_id || "—"
                            }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">Created</p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ formatDate(metaWhatsappSetting.created_at) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Last Updated
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ formatDate(metaWhatsappSetting.updated_at) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Numbers -->
            <div
                class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div
                    class="px-6 py-4 border-b border-surface-200 flex items-center justify-between"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-surface-900">
                            WhatsApp Numbers
                        </h2>

                        <p class="text-xs text-surface-500 mt-1">
                            Numbers using this Meta configuration.
                        </p>
                    </div>

                    <span class="text-xs font-medium text-surface-500">
                        {{ metaWhatsappSetting.whatsapp_numbers?.length || 0 }}
                        numbers
                    </span>
                </div>

                <div
                    v-if="metaWhatsappSetting.whatsapp_numbers?.length"
                    class="divide-y divide-surface-100"
                >
                    <div
                        v-for="number in metaWhatsappSetting.whatsapp_numbers"
                        :key="number.id"
                        class="px-6 py-4 flex items-center justify-between gap-4"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-surface-900">
                                    {{
                                        number.display_phone_number ||
                                        number.phone_number
                                    }}
                                </p>

                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium"
                                    :class="
                                        number.is_active
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-surface-100 text-surface-600'
                                    "
                                >
                                    {{
                                        number.is_active ? "Active" : "Inactive"
                                    }}
                                </span>
                            </div>

                            <p class="text-xs text-surface-500 mt-1">
                                {{ number.verified_name || "No verified name" }}
                            </p>

                            <p class="text-[11px] text-surface-400 mt-1">
                                Phone Number ID:
                                {{ number.phone_number_id }}
                            </p>
                        </div>

                        <div class="flex items-center gap-4 shrink-0">
                            <div class="text-right">
                                <p class="text-xs text-surface-500">Teams</p>

                                <p class="text-sm font-medium text-surface-900">
                                    {{ number.teams_count ?? 0 }}
                                </p>
                            </div>

                            <Link
                                :href="
                                    route(
                                        'superadmin.whatsapp-numbers.show',
                                        number.id,
                                    )
                                "
                                class="text-xs font-medium text-slate-600 hover:text-slate-700"
                            >
                                View →
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm font-medium text-surface-700">
                        No WhatsApp numbers
                    </p>

                    <p class="text-xs text-surface-500 mt-1">
                        No WhatsApp numbers are currently using this
                        configuration.
                    </p>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>