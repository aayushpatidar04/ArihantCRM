<script setup>
import { computed, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    teams: {
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

const { success, error: toastError } = useToast();

const search = ref(props.filters.search ?? "");
const status = ref(props.filters.status ?? "");
const deletingId = ref(null);

let searchTimeout = null;

function applyFilters() {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        router.get(
            route("superadmin.teams.index"),
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

function changeStatus() {
    applyFilters();
}

function clearFilters() {
    search.value = "";
    status.value = "";

    router.get(
        route("superadmin.teams.index"),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function deleteTeam(team) {
    if (!confirm(`Are you sure you want to delete "${team.name}"?`)) {
        return;
    }

    deletingId.value = team.id;

    router.delete(route("superadmin.teams.destroy", team.id), {
        preserveScroll: true,

        onSuccess: () => {
            success("Team deleted successfully.");
        },

        onError: (errors) => {
            toastError(Object.values(errors)[0] ?? "Unable to delete team.");
        },

        onFinish: () => {
            deletingId.value = null;
        },
    });
}

function statusClass(isActive) {
    return isActive ? "bg-brand-100 text-brand-700" : "bg-red-100 text-red-600";
}
</script>

<template>
    <Head title="Teams" />

    <SuperAdminLayout title="Teams">
        <template #header>
            <div>
                <h1 class="text-base font-semibold text-surface-900">
                    Teams
                </h1>

                <p class="text-xs text-surface-500 mt-0.5">
                    Manage teams and teams on the platform.
                </p>
            </div>
        </template>

        <template #actions>
            <Link
                :href="route('superadmin.teams.create')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-xl hover:bg-brand-700 transition-colors"
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
                        d="M12 4v16m8-8H4"
                    />
                </svg>

                Create Team
            </Link>
        </template>

        <div class="p-6 space-y-5">
            <!-- Filters -->
            <div class="bg-white border border-surface-100 rounded-2xl p-4">
                <div class="flex flex-col md:flex-row gap-3">
                    <!-- Search -->
                    <div class="relative flex-1">
                        <svg
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-surface-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"
                            />
                        </svg>

                        <input
                            v-model="search"
                            @input="applyFilters"
                            type="text"
                            placeholder="Search teams..."
                            class="w-full pl-10 pr-4 py-2.5 text-sm border border-surface-200 rounded-xl focus:border-brand-500 focus:ring-2 focus:ring-brand-500/10 outline-none"
                        />
                    </div>

                    <!-- Status -->
                    <select
                        v-model="status"
                        @change="changeStatus"
                        class="w-full md:w-44 px-3 py-2.5 text-sm border border-surface-200 rounded-xl focus:border-brand-500 focus:ring-2 focus:ring-brand-500/10 outline-none bg-white"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>

                    <!-- Clear -->
                    <button
                        v-if="search || status"
                        type="button"
                        @click="clearFilters"
                        class="px-4 py-2.5 text-sm text-surface-600 border border-surface-200 rounded-xl hover:bg-surface-50 transition-colors"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div
                class="bg-white border border-surface-100 rounded-2xl overflow-hidden"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-surface-100 bg-surface-50/70"
                            >
                                <th
                                    class="text-left px-5 py-3.5 text-xs font-semibold text-surface-500 uppercase tracking-wider"
                                >
                                    Team
                                </th>

                                <th
                                    class="text-left px-5 py-3.5 text-xs font-semibold text-surface-500 uppercase tracking-wider"
                                >
                                    Users
                                </th>

                                <th
                                    class="text-left px-5 py-3.5 text-xs font-semibold text-surface-500 uppercase tracking-wider"
                                >
                                    Customers
                                </th>

                                <th
                                    class="text-left px-5 py-3.5 text-xs font-semibold text-surface-500 uppercase tracking-wider"
                                >
                                    Status
                                </th>

                                <th
                                    class="text-right px-5 py-3.5 text-xs font-semibold text-surface-500 uppercase tracking-wider"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-100">
                            <tr
                                v-for="team in teams.data"
                                :key="team.id"
                                class="hover:bg-surface-50/60 transition-colors"
                            >
                                <!-- Team -->
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center shrink-0"
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
                                                    d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h2m-2 4h2m2-4h2m-2 4h2M9 21v-4h6v4"
                                                />
                                            </svg>
                                        </div>

                                        <div class="min-w-0">
                                            <p
                                                class="font-medium text-surface-900 truncate"
                                            >
                                                {{ team.name }}
                                            </p>

                                            <p
                                                class="text-xs text-surface-400 truncate"
                                            >
                                                {{ team.slug }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Users -->
                                <td class="px-5 py-4">
                                    <span class="text-surface-700 font-medium">
                                        {{ team.users_count ?? 0 }}
                                    </span>
                                </td>

                                <!-- Customers -->
                                <td class="px-5 py-4">
                                    <span class="text-surface-700 font-medium">
                                        {{ team.customers_count ?? 0 }}
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="px-5 py-4">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium',
                                            statusClass(team.is_active),
                                        ]"
                                    >
                                        <span
                                            class="w-1.5 h-1.5 rounded-full mr-1.5 bg-current"
                                        />

                                        {{
                                            team.is_active
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-4">
                                    <div
                                        class="flex justify-end items-center gap-2"
                                    >
                                        <Link
                                            :href="
                                                route(
                                                    'superadmin.teams.show',
                                                    team.id,
                                                )
                                            "
                                            class="px-3 py-1.5 text-xs font-medium text-surface-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                                        >
                                            View
                                        </Link>

                                        <Link
                                            :href="
                                                route(
                                                    'superadmin.teams.edit',
                                                    team.id,
                                                )
                                            "
                                            class="px-3 py-1.5 text-xs font-medium text-surface-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                                        >
                                            Edit
                                        </Link>

                                        <button
                                            type="button"
                                            :disabled="
                                                deletingId === team.id
                                            "
                                            @click="deleteTeam(team)"
                                            class="px-3 py-1.5 text-xs font-medium text-red-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50"
                                        >
                                            {{
                                                deletingId === team.id
                                                    ? "Deleting..."
                                                    : "Delete"
                                            }}
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty -->
                            <tr v-if="!teams.data?.length">
                                <td colspan="5" class="px-5 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <div
                                            class="w-12 h-12 rounded-2xl bg-surface-100 flex items-center justify-center mb-3"
                                        >
                                            <svg
                                                class="w-5 h-5 text-surface-400"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h2m-2 4h2m2-4h2m-2 4h2M9 21v-4h6v4"
                                                />
                                            </svg>
                                        </div>

                                        <p
                                            class="text-sm font-medium text-surface-700"
                                        >
                                            No teams found
                                        </p>

                                        <p
                                            class="text-xs text-surface-400 mt-1"
                                        >
                                            Create a team to get started.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="teams.links?.length > 3"
                    class="px-5 py-4 border-t border-surface-100 flex items-center justify-between"
                >
                    <p class="text-xs text-surface-500">
                        Showing
                        <span class="font-medium text-surface-700">
                            {{ teams.from ?? 0 }}
                        </span>
                        to
                        <span class="font-medium text-surface-700">
                            {{ teams.to ?? 0 }}
                        </span>
                        of
                        <span class="font-medium text-surface-700">
                            {{ teams.total ?? 0 }}
                        </span>
                    </p>

                    <div class="flex items-center gap-1">
                        <template
                            v-for="link in teams.links"
                            :key="link.label"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-state
                                preserve-scroll
                                class="min-w-8 h-8 px-2 flex items-center justify-center rounded-lg text-xs transition-colors"
                                :class="
                                    link.active
                                        ? 'bg-brand-600 text-white'
                                        : 'text-surface-600 hover:bg-surface-100'
                                "
                                v-html="link.label"
                            />

                            <span
                                v-else
                                class="min-w-8 h-8 px-2 flex items-center justify-center rounded-lg text-xs text-surface-300"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
