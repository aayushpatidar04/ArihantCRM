<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";

const props = defineProps({
    templates: {
        type: Object,
        required: true,
    },

    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters.search ?? "");
const status = ref(props.filters.status ?? "");
const category = ref(props.filters.category ?? "");
const enabled = ref(props.filters.enabled ?? "");

function applyFilters() {
    router.get(
        route("superadmin.whatsapp-templates.index"),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            category: category.value || undefined,
            enabled: enabled.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function statusClass(templateStatus) {
    switch ((templateStatus ?? "").toUpperCase()) {
        case "APPROVED":
            return "bg-emerald-50 text-emerald-700";

        case "PENDING":
            return "bg-amber-50 text-amber-700";

        case "REJECTED":
            return "bg-red-50 text-red-700";

        default:
            return "bg-surface-100 text-surface-600";
    }
}

function formatDate(date) {
    if (!date) return "—";

    return new Intl.DateTimeFormat("en-IN", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(date));
}

function syncTemplates(numberId) {
    router.post(
        route("superadmin.whatsapp-numbers.sync-templates", numberId),
        {},
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head title="WhatsApp Templates" />

    <SuperAdminLayout title="WhatsApp Templates">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        WhatsApp Templates
                    </h1>

                    <p class="mt-1 text-sm text-surface-500">
                        Manage Meta-approved templates for your WhatsApp
                        numbers.
                    </p>
                </div>

                <Link
                    :href="route('superadmin.whatsapp-numbers.index')"
                    class="px-4 py-2 text-sm font-medium text-surface-700 bg-white border border-slate-500 rounded-lg hover:bg-surface-50"
                >
                    WhatsApp Numbers
                </Link>
            </div>

            <!-- Filters -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm mb-6"
            >
                <div class="p-4 flex flex-col lg:flex-row gap-3">
                    <input
                        v-model="search"
                        @keyup.enter="applyFilters"
                        type="text"
                        placeholder="Search templates or numbers..."
                        class="flex-1 rounded-lg border border-surface-200 px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-500"
                    />

                    <select
                        v-model="status"
                        class="w-32 rounded-lg border border-surface-200 px-3 py-2 text-sm"
                    >
                        <option value="">All Status</option>
                        <option value="APPROVED">Approved</option>
                        <option value="PENDING">Pending</option>
                        <option value="REJECTED">Rejected</option>
                    </select>

                    <select
                        v-model="category"
                        class="w-32 rounded-lg border border-surface-200 px-3 py-2 text-sm"
                    >
                        <option value="">All Categories</option>
                        <option value="MARKETING">Marketing</option>
                        <option value="UTILITY">Utility</option>
                        <option value="AUTHENTICATION">Authentication</option>
                    </select>

                    <select
                        v-model="enabled"
                        class="w-32 rounded-lg border border-surface-200 px-3 py-2 text-sm"
                    >
                        <option value="">All Templates</option>
                        <option value="yes">Enabled</option>
                        <option value="no">Disabled</option>
                    </select>

                    <button
                        @click="applyFilters"
                        class="px-4 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-900"
                    >
                        Filter
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm overflow-hidden"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-surface-50 border-b border-surface-200"
                        >
                            <tr>
                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Template
                                </th>

                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    WhatsApp Number
                                </th>

                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Category
                                </th>

                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Status
                                </th>

                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Synced
                                </th>

                                <th
                                    class="text-right px-6 py-3 font-medium text-surface-500"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-100">
                            <tr
                                v-for="template in templates.data"
                                :key="template.id"
                                class="hover:bg-surface-50"
                            >
                                <td class="px-6 py-4">
                                    <div>
                                        <Link
                                            :href="
                                                route(
                                                    'superadmin.whatsapp-templates.show',
                                                    template.id,
                                                )
                                            "
                                            class="font-medium text-surface-900 hover:text-slate-600"
                                        >
                                            {{ template.name }}
                                        </Link>

                                        <p
                                            class="text-xs text-surface-500 mt-1"
                                        >
                                            {{ template.language }}
                                        </p>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div v-if="template.whatsapp_number">
                                        <p class="font-medium text-surface-800">
                                            {{
                                                template.whatsapp_number
                                                    .display_phone_number ||
                                                template.whatsapp_number
                                                    .phone_number
                                            }}
                                        </p>

                                        <p
                                            v-if="
                                                template.whatsapp_number
                                                    .verified_name
                                            "
                                            class="text-xs text-surface-500"
                                        >
                                            {{
                                                template.whatsapp_number
                                                    .verified_name
                                            }}
                                        </p>
                                    </div>

                                    <span v-else class="text-surface-400">
                                        —
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-surface-600">
                                    {{ template.category || "—" }}
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium"
                                            :class="
                                                statusClass(template.status)
                                            "
                                        >
                                            {{ template.status || "Unknown" }}
                                        </span>

                                        <span
                                            v-if="template.is_enabled"
                                            class="text-xs text-emerald-600"
                                        >
                                            Enabled
                                        </span>

                                        <span
                                            v-else
                                            class="text-xs text-surface-400"
                                        >
                                            Disabled
                                        </span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-xs text-surface-500">
                                    {{ formatDate(template.last_synced_at) }}
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Link
                                            :href="
                                                route(
                                                    'superadmin.whatsapp-templates.show',
                                                    template.id,
                                                )
                                            "
                                            class="px-3 py-1.5 text-xs font-medium border border-surface-200 rounded-lg hover:bg-surface-50"
                                        >
                                            View
                                        </Link>

                                        <Link
                                            :href="
                                                route(
                                                    'superadmin.whatsapp-templates.edit',
                                                    template.id,
                                                )
                                            "
                                            class="px-3 py-1.5 text-xs font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900"
                                        >
                                            Configure
                                        </Link>

                                        <button
                                            v-if="template.whatsapp_number"
                                            @click="
                                                syncTemplates(
                                                    template.whatsapp_number.id,
                                                )
                                            "
                                            class="px-3 py-1.5 text-xs font-medium border border-surface-200 rounded-lg hover:bg-surface-50"
                                        >
                                            Sync
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!templates.data.length">
                                <td
                                    colspan="6"
                                    class="px-6 py-12 text-center text-sm text-surface-500"
                                >
                                    No WhatsApp templates found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="templates.links?.length > 3"
                    class="px-6 py-4 border-t border-surface-200 flex flex-wrap gap-1"
                >
                    <template
                        v-for="(link, index) in templates.links"
                        :key="index"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            class="px-3 py-1.5 text-xs rounded-lg border"
                            :class="
                                link.active
                                    ? 'bg-slate-600 text-white border-slate-600'
                                    : 'border-surface-200 hover:bg-surface-50'
                            "
                        />

                        <span
                            v-else
                            v-html="link.label"
                            class="px-3 py-1.5 text-xs rounded-lg text-surface-300"
                        />
                    </template>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>