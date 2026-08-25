<script setup>
import { ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

import TeamAdminLayout from "@/Components/Layout/TeamAdminLayout.vue";

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },

    team: {
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

let searchTimeout = null;

const applyFilters = () => {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        router.get(
            route("team-admin.users.index"),
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
};

watch([search, status], applyFilters);

const deleteUser = (user) => {
    if (!confirm(`Are you sure you want to delete ${user.name}?`)) {
        return;
    }

    router.delete(route("team-admin.users.destroy", user.id), {
        preserveScroll: true,
    });
};

const resetTwoFactor = (user) => {
    if (
        !confirm(
            `Are you sure you want to reset Google Authenticator for ${user.name}? ` +
            'The existing authenticator will stop working immediately, ' +
            'and the user will need to set up 2FA again on their next login.'
        )
    ) {
        return;
    }

    router.post(
        route(
            'team-admin.users.reset-two-factor',
            user.id
        ),
        {},
        {
            preserveScroll: true,
        }
    );
};
</script>

<template>
    <Head title="Users" />

    <TeamAdminLayout title="Users">
        <div class="space-y-6">
            <!-- Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        Users
                    </h1>

                    <p class="text-sm text-surface-500 mt-1">
                        Manage users belonging to
                        {{ team.name }}.
                    </p>
                </div>

                <Link
                    :href="route('team-admin.users.create')"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-900 transition"
                >
                    + Add User
                </Link>
            </div>

            <!-- Filters -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm p-4"
            >
                <div class="grid grid-cols-1 md:grid-cols-[1fr_180px] gap-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search name, email or phone..."
                        class="w-full rounded-lg border border-surface-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />

                    <select
                        v-model="status"
                        class="rounded-lg border border-surface-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
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
                    <table class="min-w-full divide-y divide-surface-200">
                        <thead class="bg-surface-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold text-surface-500 uppercase tracking-wider"
                                >
                                    User
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold text-surface-500 uppercase tracking-wider"
                                >
                                    Phone
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold text-surface-500 uppercase tracking-wider"
                                >
                                    Role
                                </th>

                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold text-surface-500 uppercase tracking-wider"
                                >
                                    Status
                                </th>

                                <th
                                    class="px-6 py-3 text-right text-xs font-semibold text-surface-500 uppercase tracking-wider"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-100">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="hover:bg-surface-50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 rounded-full bg-slate-50 text-slate-700 flex items-center justify-center text-sm font-semibold"
                                        >
                                            {{
                                                user.name
                                                    ?.charAt(0)
                                                    ?.toUpperCase()
                                            }}
                                        </div>

                                        <div>
                                            <p
                                                class="text-sm font-medium text-surface-900"
                                            >
                                                {{ user.name }}
                                            </p>

                                            <p class="text-xs text-surface-500">
                                                {{ user.email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-sm text-surface-600">
                                    {{ user.phone || "—" }}
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="inline-flex px-2 py-1 rounded-full bg-surface-100 text-surface-700 text-xs font-medium"
                                    >
                                        {{ role.name }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex px-2 py-1 rounded-full text-xs font-medium"
                                        :class="
                                            user.is_active
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-surface-100 text-surface-500'
                                        "
                                    >
                                        {{
                                            user.is_active
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <div
                                        class="flex items-center justify-end gap-3"
                                    >
                                        <Link
                                            v-if="!user.roles.some(r => r.name === 'team_admin')"
                                            :href="route('team-admin.users.edit', user.id)"
                                            class="text-sm font-medium text-slate-600 hover:text-slate-700"
                                        >
                                            Edit
                                        </Link>

                                        <button v-if="!user.roles.some(r => r.name === 'team_admin')"
                                            type="button"
                                            @click="deleteUser(user)"
                                            class="text-sm font-medium text-red-600 hover:text-red-700"
                                        >
                                            Delete
                                        </button>

                                        <button
                                            type="button"
                                            @click="resetTwoFactor(user)"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md
                                                bg-orange-50 text-orange-700 hover:bg-orange-100"
                                        >
                                            Reset 2FA
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!users.data?.length">
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <p
                                        class="text-sm font-medium text-surface-700"
                                    >
                                        No users found.
                                    </p>

                                    <p class="text-xs text-surface-500 mt-1">
                                        Try changing your filters or add a new
                                        user.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="users.links && users.links.length > 3"
                    class="px-6 py-4 border-t border-surface-200 flex flex-wrap gap-2"
                >
                    <Link
                        v-for="link in users.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs',
                            link.active
                                ? 'bg-slate-600 text-white'
                                : link.url
                                  ? 'bg-surface-100 text-surface-700 hover:bg-surface-200'
                                  : 'bg-surface-50 text-surface-300 cursor-not-allowed',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </TeamAdminLayout>
</template>