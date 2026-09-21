<script setup>
import { ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

import TeamAdminLayout from "@/Components/Layout/TeamAdminLayout.vue";

const props = defineProps({
    customers: {
        type: Object,
        required: true,
    },

    team: {
        type: Object,
        required: true,
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
        }),
    },
});

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

const search = ref(props.filters?.search ?? "");

let searchTimeout = null;

watch(search, (value) => {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        router.get(
            route("team-admin.customers.index"),
            {
                search: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 400);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const formatDate = (value) => {
    if (!value) {
        return "—";
    }

    return new Date(value).toLocaleString();
};

const statusClass = (status) => {
    switch (status) {
        case "active":
            return "bg-emerald-50 text-emerald-700";

        case "inactive":
            return "bg-surface-100 text-surface-600";

        case "blocked":
            return "bg-red-50 text-red-700";

        default:
            return "bg-surface-100 text-surface-600";
    }
};

const fetchLeadOpen = ref(false);

const fetchLeadForm = useForm({
    lead_id: "",
});

const openFetchLeadModal = () => {
    fetchLeadForm.clearErrors();
    fetchLeadForm.reset();

    fetchLeadOpen.value = true;
};

const closeFetchLeadModal = () => {
    if (fetchLeadForm.processing) {
        return;
    }

    fetchLeadOpen.value = false;

    fetchLeadForm.clearErrors();
    fetchLeadForm.reset();
};

const fetchBitrixLead = () => {
    fetchLeadForm.post(route("customers.fetch-bitrix-lead"), {
        preserveScroll: true,

        onSuccess: () => {
            closeFetchLeadModal();
        },
    });
};
</script>

<template>
    <Head title="Customers" />

    <TeamAdminLayout title="Customers">
        <div class="space-y-6">
            <!-- Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        Customers
                    </h1>

                    <p class="text-sm text-surface-500 mt-1">
                        Manage customers for
                        <strong>{{ team.name }}</strong
                        >.
                    </p>
                </div>

                <Link
                    :href="route('team-admin.customers.create')"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-900 transition"
                >
                    + Add Customer
                </Link>

                <button
                    type="button"
                    @click="openFetchLeadModal"
                    class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100"
                >
                    <Download class="h-4 w-4" />

                    Fetch Bitrix Lead
                </button>
            </div>

            <!-- Search -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm p-4"
            >
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="flex-1">
                        <label for="customer-search" class="sr-only">
                            Search customers
                        </label>

                        <input
                            id="customer-search"
                            v-model="search"
                            type="text"
                            placeholder="Search by name, phone or email..."
                            class="w-full px-4 py-2.5 rounded-lg border border-surface-200 text-sm text-surface-900 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                        />
                    </div>

                    <button
                        v-if="search"
                        type="button"
                        @click="search = ''"
                        class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg border border-surface-200 bg-white text-sm text-surface-600 hover:bg-surface-50 transition"
                    >
                        Clear
                    </button>
                </div>

                <p v-if="search" class="text-xs text-surface-500 mt-2">
                    Searching for:
                    <span class="font-medium text-surface-700">
                        "{{ search }}"
                    </span>
                </p>
            </div>

            <!-- Table -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm overflow-hidden"
            >
                <div v-if="customers.data?.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-surface-50 border-b border-surface-200"
                        >
                            <tr>
                                <th
                                    class="text-left px-5 py-3 text-xs font-medium text-surface-500"
                                >
                                    Customer
                                </th>

                                <th
                                    class="text-left px-5 py-3 text-xs font-medium text-surface-500"
                                >
                                    Phone
                                </th>

                                <th
                                    class="text-left px-5 py-3 text-xs font-medium text-surface-500"
                                >
                                    Assigned To
                                </th>

                                <th
                                    class="text-left px-5 py-3 text-xs font-medium text-surface-500"
                                >
                                    Status
                                </th>

                                <th
                                    class="text-left px-5 py-3 text-xs font-medium text-surface-500"
                                >
                                    Last Updated
                                </th>

                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-100">
                            <tr
                                v-for="customer in customers.data"
                                :key="customer.id"
                                class="hover:bg-surface-50 transition"
                            >
                                <!-- Customer -->
                                <td class="px-5 py-4">
                                    <div>
                                        <p class="font-medium text-surface-900">
                                            {{ customer.name }} - #{{
                                                customer.bitrix_lead_id
                                            }}
                                        </p>

                                        <p
                                            v-if="customer.email"
                                            class="text-xs text-surface-500 mt-0.5"
                                        >
                                            {{ customer.email }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Phone -->
                                <td class="px-5 py-4 text-surface-700">
                                    {{ customer.phone }}
                                </td>

                                <!-- Assigned To -->
                                <td class="px-5 py-4">
                                    <p
                                        v-if="customer.assigned_to"
                                        class="text-sm text-surface-700"
                                    >
                                        {{ customer.assigned_to.name }}
                                    </p>

                                    <p v-else class="text-xs text-surface-400">
                                        Unassigned
                                    </p>
                                </td>

                                <!-- Status -->
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium capitalize"
                                        :class="statusClass(customer.status)"
                                    >
                                        {{ customer.status }}
                                    </span>

                                    <span
                                        v-if="
                                            customer.unread_messages_count > 0
                                        "
                                        class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700"
                                    >
                                        {{ customer.unread_messages_count }}
                                        unread
                                    </span>
                                </td>

                                <!-- Last Updated -->
                                <td class="px-5 py-4 text-xs text-surface-500">
                                    {{ formatDate(customer.updated_at) }}
                                </td>

                                <!-- Action -->
                                <td class="px-5 py-4 text-right">
                                    <Link
                                        :href="
                                            route(
                                                'team-admin.customers.show',
                                                customer.id,
                                            )
                                        "
                                        class="text-xs font-medium text-slate-600 hover:text-slate-900"
                                    >
                                        View →
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty -->
                <div v-else class="px-6 py-12 text-center">
                    <p class="text-sm font-medium text-surface-700">
                        {{
                            search
                                ? "No customers found matching your search."
                                : "No customers found."
                        }}
                    </p>

                    <p v-if="!search" class="text-xs text-surface-500 mt-1">
                        Add the first customer to this team.
                    </p>

                    <Link
                        v-if="!search"
                        :href="route('team-admin.customers.create')"
                        class="inline-flex mt-4 px-4 py-2 rounded-lg bg-slate-600 hover:bg-slate-900 text-white text-sm"
                    >
                        Add Customer
                    </Link>

                    <button
                        v-else
                        type="button"
                        @click="search = ''"
                        class="inline-flex mt-4 px-4 py-2 rounded-lg bg-slate-600 hover:bg-slate-900 text-white text-sm"
                    >
                        Clear Search
                    </button>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="customers.links?.length > 3"
                class="flex flex-wrap gap-1"
            >
                <template v-for="(link, index) in customers.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="px-3 py-1.5 rounded-lg text-xs border"
                        :class="
                            link.active
                                ? 'bg-slate-600 text-white border-slate-600'
                                : 'bg-white text-surface-600 border-surface-200 hover:bg-surface-50'
                        "
                        v-html="link.label"
                    />

                    <span
                        v-else
                        class="px-3 py-1.5 text-xs text-surface-400"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>

        <Modal
            :show="fetchLeadOpen"
            max-width="md"
            @close="closeFetchLeadModal"
        >
            <form
                class="overflow-hidden rounded-xl bg-white shadow-xl"
                @submit.prevent="fetchBitrixLead"
            >
                <!-- Header -->
                <div
                    class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">
                            Fetch Bitrix Lead
                        </h2>

                        <p class="mt-1 text-sm leading-5 text-gray-500">
                            Enter a Bitrix Lead ID to create or update the
                            customer.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        @click="closeFetchLeadModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Body -->
                <div class="px-6 py-6">
                    <label
                        for="bitrix-lead-id"
                        class="block text-sm font-medium text-surface-700 mb-1.5"
                    >
                        Bitrix Lead ID
                    </label>

                    <input
                        id="bitrix-lead-id"
                        v-model="fetchLeadForm.lead_id"
                        type="number"
                        min="1"
                        inputmode="numeric"
                        class="w-full rounded-lg border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                        placeholder="e.g. 199103"
                        required
                    />

                    <InputError
                        class="mt-2"
                        :message="fetchLeadForm.errors.lead_id"
                    />

                    <p
                        v-if="!fetchLeadForm.errors.lead_id"
                        class="mt-2 text-xs text-gray-500"
                    >
                        The lead will be fetched from Bitrix and automatically
                        assigned to its current executive and primary team.
                    </p>
                </div>

                <!-- Footer -->
                <div
                    class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4"
                >
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="fetchLeadForm.processing"
                        @click="closeFetchLeadModal"
                    >
                        Cancel
                    </button>

                    <PrimaryButton
                        type="submit"
                        :disabled="
                            fetchLeadForm.processing || !fetchLeadForm.lead_id
                        "
                        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Download class="mr-2 h-4 w-4" />

                        {{
                            fetchLeadForm.processing
                                ? "Fetching..."
                                : "Fetch Lead"
                        }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </TeamAdminLayout>
</template>
