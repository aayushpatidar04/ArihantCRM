<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";
import { ref, watch } from "vue";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    numbers: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            search: "",
            status: "",
        }),
    },
});

const { success, error, info } = useToast();

const search = ref(props.filters.search || "");
const status = ref(props.filters.status || "");

let searchTimeout = null;

function applyFilters() {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        router.get(
            route("superadmin.whatsapp-numbers.index"),
            {
                search: search.value || undefined,
                status: status.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 300);
}

watch([search, status], applyFilters);

function formatDate(dateString) {
    if (!dateString) return "—";

    return new Intl.DateTimeFormat("en-IN", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(dateString));
}

function deleteNumber(number) {
    if (
        !confirm(
            `Are you sure you want to delete ${number.display_phone_number || number.phone_number}?`,
        )
    ) {
        return;
    }

    router.delete(route("superadmin.whatsapp-numbers.destroy", number.id), {
        preserveScroll: true,
    });
}

function toggleStatus(number) {
    router.patch(
        route("superadmin.whatsapp-numbers.toggle", number.id),
        {},
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head title="WhatsApp Numbers" />

    <SuperAdminLayout title="WhatsApp Numbers">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        WhatsApp Numbers
                    </h1>

                    <p class="mt-1 text-sm text-surface-500">
                        Manage WhatsApp Cloud API numbers and their Meta
                        configurations.
                    </p>
                </div>

                <Link
                    :href="route('superadmin.whatsapp-numbers.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 transition"
                >
                    <span class="text-lg leading-none">+</span>
                    Add WhatsApp Number
                </Link>
            </div>

            <!-- Filters -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm mb-6"
            >
                <div class="p-4 flex flex-col md:flex-row gap-3">
                    <div class="flex-1">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by phone number, verified name..."
                            class="w-full px-3 py-2 text-sm border border-surface-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <select
                        v-model="status"
                        class="w-32 px-3 py-2 text-sm border border-surface-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
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
                                    class="text-left px-6 py-3 text-xs font-semibold text-surface-500 uppercase"
                                >
                                    Number
                                </th>

                                <th
                                    class="text-left px-6 py-3 text-xs font-semibold text-surface-500 uppercase"
                                >
                                    Meta Configuration
                                </th>

                                <th
                                    class="text-left px-6 py-3 text-xs font-semibold text-surface-500 uppercase"
                                >
                                    Team
                                </th>

                                <th
                                    class="text-left px-6 py-3 text-xs font-semibold text-surface-500 uppercase"
                                >
                                    Status
                                </th>

                                <th
                                    class="text-left px-6 py-3 text-xs font-semibold text-surface-500 uppercase"
                                >
                                    Last Webhook
                                </th>

                                <th
                                    class="text-right px-6 py-3 text-xs font-semibold text-surface-500 uppercase"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-100">
                            <tr
                                v-for="number in numbers.data"
                                :key="number.id"
                                class="hover:bg-surface-50 transition"
                            >
                                <!-- Number -->
                                <td class="px-6 py-4">
                                    <Link
                                        :href="
                                            route(
                                                'superadmin.whatsapp-numbers.show',
                                                number.id,
                                            )
                                        "
                                        class="font-medium text-surface-900 hover:text-slate-600"
                                    >
                                        {{
                                            number.display_phone_number ||
                                            number.phone_number
                                        }}
                                    </Link>

                                    <p
                                        v-if="number.verified_name"
                                        class="text-xs text-surface-500 mt-1"
                                    >
                                        {{ number.verified_name }}
                                    </p>

                                    <p
                                        class="text-[11px] text-surface-400 mt-1"
                                    >
                                        ID: {{ number.phone_number_id }}
                                    </p>
                                </td>

                                <!-- Meta -->
                                <td class="px-6 py-4">
                                    <template
                                        v-if="number.meta_whatsapp_setting"
                                    >
                                        <p class="font-medium text-surface-900">
                                            {{
                                                number.meta_whatsapp_setting
                                                    .name
                                            }}
                                        </p>

                                        <p
                                            class="text-xs text-surface-500 mt-1"
                                        >
                                            WABA:
                                            {{ number.waba_id || "—" }}
                                        </p>
                                    </template>

                                    <span v-else class="text-xs text-red-500">
                                        Not configured
                                    </span>
                                </td>

                                <!-- Team -->
                                <td class="px-6 py-4">
                                    <template v-if="number.teams && number.teams.length > 0">
                                        <span class="font-medium text-slate-600">Assigned</span>
                                    </template>

                                    <span
                                        v-else
                                        class="text-xs text-surface-400"
                                    >
                                        Unassigned
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-4">
                                    <button
                                        type="button"
                                        @click="toggleStatus(number)"
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                                        :class="
                                            number.is_active
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-surface-100 text-surface-600'
                                        "
                                    >
                                        {{
                                            number.is_active
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </button>
                                </td>

                                <!-- Webhook -->
                                <td class="px-6 py-4">
                                    <span
                                        v-if="number.last_webhook_at"
                                        class="text-xs text-surface-600"
                                    >
                                        {{ formatDate(number.last_webhook_at) }}
                                    </span>

                                    <span
                                        v-else
                                        class="text-xs text-surface-400"
                                    >
                                        Never
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Link
                                            :href="
                                                route(
                                                    'superadmin.whatsapp-numbers.show',
                                                    number.id,
                                                )
                                            "
                                            class="px-3 py-1.5 text-xs font-medium text-surface-700 bg-surface-100 rounded-lg hover:bg-surface-200"
                                        >
                                            View
                                        </Link>

                                        <Link
                                            :href="route(
                                                'superadmin.whatsapp-numbers.sync-templates',
                                                number.id
                                            )"
                                            method="post"
                                            as="button"
                                            preserve-scroll
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5
                                                text-xs font-medium text-slate-700
                                                bg-slate-50 border border-slate-100
                                                rounded-lg hover:bg-slate-100 transition"
                                        >
                                            <svg
                                                class="w-3.5 h-3.5"
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

                                        <Link
                                            :href="
                                                route(
                                                    'superadmin.whatsapp-numbers.edit',
                                                    number.id,
                                                )
                                            "
                                            class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-50 rounded-lg hover:bg-slate-100"
                                        >
                                            Edit
                                        </Link>

                                        <button
                                            type="button"
                                            @click="deleteNumber(number)"
                                            class="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!numbers.data?.length">
                                <td
                                    colspan="6"
                                    class="px-6 py-12 text-center text-sm text-surface-500"
                                >
                                    No WhatsApp numbers found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="numbers.links?.length > 3"
                    class="px-6 py-4 border-t border-surface-200 flex items-center justify-between"
                >
                    <p class="text-xs text-surface-500">
                        Showing
                        {{ numbers.from || 0 }}
                        to
                        {{ numbers.to || 0 }}
                        of
                        {{ numbers.total || 0 }}
                    </p>

                    <div class="flex items-center gap-1">
                        <template
                            v-for="link in numbers.links"
                            :key="link.label"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-state
                                preserve-scroll
                                class="px-3 py-1.5 text-xs rounded-lg"
                                :class="
                                    link.active
                                        ? 'bg-slate-600 text-white'
                                        : 'text-surface-600 hover:bg-surface-100'
                                "
                                v-html="link.label"
                            />

                            <span
                                v-else
                                class="px-3 py-1.5 text-xs text-surface-300"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>