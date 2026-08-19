<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import ExecutiveLayout from "@/Components/Layout/ExecutiveLayout.vue";
import { Download, X } from "lucide-vue-next";
import Modal from "@/Components/Modal.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

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

/*
|--------------------------------------------------------------------------
| Phone masking
|--------------------------------------------------------------------------
|
| The backend should ideally NOT send the phone number at all.
| This is only an additional UI safeguard.
|
*/
const maskedPhone = (phone) => {
    if (!phone) {
        return "Hidden";
    }

    const value = String(phone);

    if (value.length <= 4) {
        return "••••";
    }

    return `${"•".repeat(Math.max(0, value.length - 4))}${value.slice(-4)}`;
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

    <ExecutiveLayout title="Customers">
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
                        Customers assigned to you or previously owned by you.
                    </p>
                </div>

                <div>
                    <Link
                        :href="route('executive.customers.create')"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-900 transition me-2"
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
            </div>

            <!-- Search -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm p-4"
            >
                <form
                    :action="route('executive.customers.index')"
                    method="get"
                    class="flex flex-col sm:flex-row gap-3"
                >
                    <input
                        type="text"
                        name="search"
                        :value="filters.search"
                        placeholder="Search by name or email..."
                        class="flex-1 rounded-lg border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />

                    <button
                        type="submit"
                        class="px-5 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-900 transition"
                    >
                        Search
                    </button>

                    <Link
                        v-if="filters.search"
                        :href="route('executive.customers.index')"
                        class="px-5 py-2 rounded-lg border border-surface-200 bg-white text-surface-600 text-sm font-medium hover:bg-surface-50 text-center"
                    >
                        Clear
                    </Link>
                </form>
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
                                    Relationship
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
                                            {{ customer.name }}
                                        </p>

                                        <p
                                            v-if="customer.email"
                                            class="text-xs text-surface-500 mt-0.5"
                                        >
                                            {{ customer.email }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Masked phone -->
                                <td class="px-5 py-4 text-surface-500">
                                    {{ maskedPhone(customer.phone) }}

                                    <p
                                        class="text-[10px] text-surface-400 mt-0.5"
                                    >
                                        Phone number hidden
                                    </p>
                                </td>

                                <!-- Relationship -->
                                <td class="px-5 py-4">
                                    <span
                                        v-if="
                                            customer.assigned_to &&
                                            customer.assigned_to.id ===
                                                $page.props.auth.user.id
                                        "
                                        class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700"
                                    >
                                        Assigned to me
                                    </span>

                                    <span
                                        v-else-if="
                                            customer.old_owner &&
                                            customer.old_owner.id ===
                                                $page.props.auth.user.id
                                        "
                                        class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700"
                                    >
                                        Previous owner
                                    </span>

                                    <span
                                        v-else
                                        class="text-xs text-surface-400"
                                    >
                                        —
                                    </span>
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

                                <!-- Updated -->
                                <td class="px-5 py-4 text-xs text-surface-500">
                                    {{ formatDate(customer.updated_at) }}
                                </td>

                                <!-- Action -->
                                <td class="px-5 py-4 text-right">
                                    <Link
                                        :href="
                                            route(
                                                'executive.customers.show',
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
                        No customers found.
                    </p>

                    <p class="text-xs text-surface-500 mt-1">
                        {{
                            filters.search
                                ? "Try a different search."
                                : "You don't have any customers assigned to you yet."
                        }}
                    </p>

                    <Link
                        v-if="!filters.search"
                        :href="route('executive.customers.create')"
                        class="inline-flex mt-4 px-4 py-2 rounded-lg bg-slate-600 hover:bg-slate-900 text-white text-sm"
                    >
                        Add Customer
                    </Link>
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
    </ExecutiveLayout>
</template>
