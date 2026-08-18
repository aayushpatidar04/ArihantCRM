<script setup>
import { computed, ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

import TeamAdminLayout from "@/Components/Layout/TeamAdminLayout.vue";

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },

    team: {
        type: Object,
        required: true,
    },

    executives: {
        type: Array,
        default: () => [],
    },
});

const customer = computed(() => props.customer);

const assignedUser = computed(() => customer.value?.assigned_to ?? null);

const oldOwner = computed(() => customer.value?.old_owner ?? null);

const statusClasses = computed(() => {
    switch (customer.value?.status) {
        case "active":
            return "bg-emerald-50 text-emerald-700";

        case "inactive":
            return "bg-surface-100 text-surface-600";

        case "blocked":
            return "bg-red-50 text-red-700";

        default:
            return "bg-surface-100 text-surface-600";
    }
});

const formatDate = (value) => {
    if (!value) {
        return "—";
    }

    return new Date(value).toLocaleString();
};

const displayPhone = computed(() => {
    if (!customer.value?.phone) {
        return "—";
    }

    return customer.value.phone.startsWith("+")
        ? customer.value.phone
        : `+${customer.value.phone}`;
});

const showAssignModal = ref(false);

const assignForm = useForm({
    assigned_to: props.customer.assigned_to?.id ?? "",
});

const openAssignModal = () => {
    assignForm.clearErrors();

    assignForm.assigned_to =
        props.customer.assigned_to?.id ?? "";

    showAssignModal.value = true;
};

const closeAssignModal = () => {
    if (assignForm.processing) {
        return;
    }

    showAssignModal.value = false;
};

const assignCustomer = () => {
    assignForm.post(
        route(
            "team-admin.customers.assign",
            props.customer.id
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                showAssignModal.value = false;
            },
        }
    );
};
</script>

<template>
    <Head :title="customer.name" />

    <TeamAdminLayout title="Customer Details">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div>
                <Link
                    :href="route('team-admin.customers.index')"
                    class="text-xs text-surface-500 hover:text-surface-800"
                >
                    ← Back to Customers
                </Link>

                <div
                    class="mt-3 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4"
                >
                    <div>
                        <h1 class="text-xl font-semibold text-surface-900">
                            {{ customer.name }}
                        </h1>

                        <p class="text-sm text-surface-500 mt-1">
                            Customer details and ownership information.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium capitalize"
                            :class="statusClasses"
                        >
                            {{ customer.status }}
                        </span>

                        <Link
                            :href="
                                route('team-admin.customers.edit', customer.id)
                            "
                            class="px-3 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-900 transition"
                        >
                            Edit
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Basic Information -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Customer Information
                    </h2>
                </div>

                <div
                    class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6"
                >
                    <div>
                        <p class="text-xs text-surface-500 mb-1">Name</p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ customer.name || "—" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">Phone</p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ displayPhone }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">Email</p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ customer.email || "—" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">Status</p>

                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                            :class="statusClasses"
                        >
                            {{ customer.status }}
                        </span>
                    </div>

                    <div class="md:col-span-2">
                        <p class="text-xs text-surface-500 mb-1">Notes</p>

                        <p class="text-sm text-surface-700 whitespace-pre-line">
                            {{ customer.notes || "No notes available." }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Ownership -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div
                    class="px-6 py-4 border-b border-surface-200 flex items-center justify-between gap-4"
                >
                    <div>
                        <h2
                            class="text-sm font-semibold text-surface-900"
                        >
                            Customer Ownership
                        </h2>

                        <p class="text-xs text-surface-500 mt-1">
                            Current and previous ownership information.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="openAssignModal"
                        class="px-3 py-2 rounded-lg bg-slate-600 text-white text-xs font-medium hover:bg-slate-900 transition"
                    >
                        Reassign Customer
                    </button>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Assigned To -->
                    <div class="rounded-xl border border-surface-200 p-4">
                        <p
                            class="text-[11px] uppercase tracking-wider text-surface-400 font-medium"
                        >
                            Assigned Executive
                        </p>

                        <div class="mt-3">
                            <p class="text-sm font-semibold text-surface-900">
                                {{ assignedUser?.name || "Not assigned" }}
                            </p>

                            <p
                                v-if="assignedUser?.email"
                                class="text-xs text-surface-500 mt-1"
                            >
                                {{ assignedUser.email }}
                            </p>

                            <p
                                v-if="assignedUser?.team?.name"
                                class="text-xs text-surface-500 mt-1"
                            >
                                Team:
                                {{ assignedUser.team.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Old Owner -->
                    <div class="rounded-xl border border-surface-200 p-4">
                        <p
                            class="text-[11px] uppercase tracking-wider text-surface-400 font-medium"
                        >
                            Previous Owner
                        </p>

                        <div class="mt-3">
                            <p class="text-sm font-semibold text-surface-900">
                                {{ oldOwner?.name || "No previous owner" }}
                            </p>

                            <p
                                v-if="oldOwner?.email"
                                class="text-xs text-surface-500 mt-1"
                            >
                                {{ oldOwner.email }}
                            </p>

                            <p
                                v-if="oldOwner?.team?.name"
                                class="text-xs text-surface-500 mt-1"
                            >
                                Team:
                                {{ oldOwner.team.name }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bitrix Information -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Bitrix Information
                    </h2>
                </div>

                <div
                    class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6"
                >
                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Bitrix Lead ID
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ customer.bitrix_lead_id || "—" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Bitrix Assigned By
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ customer.bitrix_assigned_by_id || "—" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Bitrix Created At
                        </p>

                        <p class="text-sm text-surface-700">
                            {{ formatDate(customer.bitrix_created_at) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">Last Synced</p>

                        <p class="text-sm text-surface-700">
                            {{ formatDate(customer.bitrix_synced_at) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Activity -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Activity
                    </h2>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Last Contacted
                        </p>

                        <p class="text-sm text-surface-700">
                            {{ formatDate(customer.last_contacted_at) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Customer Since
                        </p>

                        <p class="text-sm text-surface-700">
                            {{ formatDate(customer.created_at) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tags -->
            <div
                v-if="customer.tags?.length"
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">Tags</h2>
                </div>

                <div class="p-6 flex flex-wrap gap-2">
                    <span
                        v-for="tag in customer.tags"
                        :key="tag"
                        class="px-2.5 py-1 rounded-full bg-surface-100 text-surface-700 text-xs"
                    >
                        {{ tag }}
                    </span>
                </div>
            </div>

        </div>
        <!-- Assign Customer Modal -->
        <div
            v-if="showAssignModal"
            class="fixed inset-0 z-50 flex items-center justify-center"
        >
            <!-- Overlay -->
            <div
                class="absolute inset-0 bg-black/40"
                @click="closeAssignModal"
            ></div>

            <!-- Modal -->
            <div style="width: 500px;"
                class="relative bg-white rounded-xl shadow-xl border border-surface-200"
            >
                <!-- Header -->
                <div
                    class="px-6 py-4 border-b border-surface-200"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2
                                class="text-sm font-semibold text-surface-900"
                            >
                                Reassign Customer
                            </h2>

                            <p class="text-xs text-surface-500 mt-1">
                                Assign
                                <strong>{{ customer.name }}</strong>
                                to an executive in
                                <strong>{{ team.name }}</strong>.
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="closeAssignModal"
                            class="text-surface-400 hover:text-surface-700 text-lg"
                        >
                            ×
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-5">
                    <div>
                        <label
                            class="block text-xs font-medium text-surface-700 mb-1"
                        >
                            Executive
                        </label>

                        <select
                            v-model="assignForm.assigned_to"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        >
                            <option value="">
                                Select executive
                            </option>

                            <option
                                v-for="executive in executives"
                                :key="executive.id"
                                :value="executive.id"
                            >
                                {{ executive.name }}
                                <template v-if="executive.email">
                                    — {{ executive.email }}
                                </template>
                            </option>
                        </select>

                        <p
                            v-if="assignForm.errors.assigned_to"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ assignForm.errors.assigned_to }}
                        </p>
                    </div>

                    <!-- Information -->
                    <div
                        class="rounded-lg bg-amber-50 border border-amber-100 p-3"
                    >
                        <p class="text-xs text-amber-800">
                            Reassigning this customer will make the selected
                            executive the current owner. The previous owner
                            will be retained as the customer's previous owner.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="px-6 py-4 border-t border-surface-200 flex justify-end gap-3"
                >
                    <button
                        type="button"
                        @click="closeAssignModal"
                        :disabled="assignForm.processing"
                        class="px-4 py-2 rounded-lg border border-surface-200 text-sm text-surface-700 hover:bg-surface-50 disabled:opacity-50"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        @click="assignCustomer"
                        :disabled="
                            assignForm.processing ||
                            !assignForm.assigned_to
                        "
                        class="px-4 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-900 disabled:opacity-50"
                    >
                        {{
                            assignForm.processing
                                ? "Assigning..."
                                : "Assign Customer"
                        }}
                    </button>
                </div>
            </div>
        </div>
    </TeamAdminLayout>
</template>