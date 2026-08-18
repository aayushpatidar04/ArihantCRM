<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";
import { ref } from "vue";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },

    filters: {
        type: Object,
        default: () => ({}),
    },
});

const { success, error, info } = useToast();

const search = ref(props.filters.search ?? "");
const status = ref(props.filters.status ?? "");

function applyFilters() {
    router.get(
        route("superadmin.meta-whatsapp-settings.index"),
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function deleteSetting(setting) {
    if (!confirm(`Delete Meta app "${setting.name}"?`)) {
        return;
    }

    router.delete(
        route("superadmin.meta-whatsapp-settings.destroy", setting.id),
    );
}

function formatDate(dateString) {
    if (!dateString) return "—";

    return new Intl.DateTimeFormat("en-IN", {
        dateStyle: "medium",
    }).format(new Date(dateString));
}
</script>

<template>
    <Head title="Meta WhatsApp Apps" />

    <SuperAdminLayout title="Meta WhatsApp Apps">
        <div class="p-6">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6"
            >
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        Meta WhatsApp Apps
                    </h1>

                    <p class="mt-1 text-sm text-surface-500">
                        Manage Meta applications used by your WhatsApp numbers.
                    </p>
                </div>

                <Link
                    :href="route('superadmin.meta-whatsapp-settings.create')"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 transition"
                >
                    + Add Meta App
                </Link>
            </div>

            <!-- Filters -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm mb-6"
            >
                <div class="p-4 flex flex-col md:flex-row gap-3">
                    <input
                        v-model="search"
                        @keyup.enter="applyFilters"
                        type="text"
                        placeholder="Search app name or App ID..."
                        class="flex-1 rounded-lg border border-surface-200 px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-500"
                    />

                    <select
                        v-model="status"
                        @change="applyFilters"
                        class="w-32 rounded-lg border border-surface-200 px-3 py-2 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>

                    <button
                        type="button"
                        @click="applyFilters"
                        class="px-4 py-2 text-sm font-medium bg-surface-900 text-white rounded-lg hover:bg-surface-800"
                    >
                        Search
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm overflow-hidden"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead
                            class="bg-surface-50 border-b border-surface-200"
                        >
                            <tr>
                                <th class="table-heading">Name</th>

                                <th class="table-heading">App ID</th>

                                <th class="table-heading">WhatsApp Numbers</th>

                                <th class="table-heading">Status</th>

                                <th class="table-heading">Created</th>

                                <th class="table-heading text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-100">
                            <tr
                                v-for="setting in settings.data"
                                :key="setting.id"
                                class="hover:bg-surface-50"
                            >
                                <td class="table-cell">
                                    <div class="font-medium text-surface-900">
                                        {{ setting.name }}
                                    </div>
                                </td>

                                <td class="table-cell">
                                    <code class="text-xs text-surface-600">
                                        {{ setting.app_id }}
                                    </code>
                                </td>

                                <td class="table-cell">
                                    <span class="text-sm font-medium">
                                        {{ setting.whatsapp_numbers_count }}
                                    </span>
                                </td>

                                <td class="table-cell">
                                    <span
                                        class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium"
                                        :class="
                                            setting.is_active
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-surface-100 text-surface-600'
                                        "
                                    >
                                        {{
                                            setting.is_active
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </span>
                                </td>

                                <td class="table-cell">
                                    {{ formatDate(setting.created_at) }}
                                </td>

                                <td class="table-cell text-right">
                                    <div
                                        class="flex justify-end items-center gap-2"
                                    >
                                        <Link
                                            :href="
                                                route(
                                                    'superadmin.meta-whatsapp-settings.show',
                                                    setting.id,
                                                )
                                            "
                                            class="px-3 py-1.5 text-xs font-medium text-surface-700 bg-surface-100 rounded-lg hover:bg-surface-200"
                                        >
                                            View
                                        </Link>
                                        <Link
                                            :href="
                                                route(
                                                    'superadmin.meta-whatsapp-settings.edit',
                                                    setting.id,
                                                )
                                            "
                                            class="px-3 py-1.5 text-xs font-medium text-surface-700 bg-surface-100 rounded-lg hover:bg-surface-200"
                                        >
                                            Edit
                                        </Link>

                                        <button
                                            type="button"
                                            @click="deleteSetting(setting)"
                                            class="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100"
                                            :disabled="
                                                setting.whatsapp_numbers_count >
                                                0
                                            "
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-if="
                                    !settings.data || settings.data.length === 0
                                "
                            >
                                <td
                                    colspan="6"
                                    class="px-6 py-12 text-center text-sm text-surface-500"
                                >
                                    No Meta WhatsApp apps found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="settings.links?.length > 3"
                    class="px-6 py-4 border-t border-surface-200 flex flex-wrap gap-2"
                >
                    <Link
                        v-for="link in settings.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-3 py-1.5 rounded-lg text-sm border"
                        :class="[
                            link.active
                                ? 'bg-slate-600 text-white border-slate-600'
                                : 'bg-white text-surface-600 border-surface-200',
                            !link.url ? 'opacity-50 pointer-events-none' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<style scoped>
.table-heading {
    @apply px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600;
}

.table-cell {
    @apply px-6 py-4 whitespace-nowrap;
}
</style>
