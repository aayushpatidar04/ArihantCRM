<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

import ExecutiveLayout from "@/Components/Layout/ExecutiveLayout.vue";

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },

    team: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.customer.name ?? "",
    email: props.customer.email ?? "",
});

const formatDate = (value) => {
    if (!value) {
        return "—";
    }

    return new Date(value).toLocaleString();
};

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

const submit = () => {
    form.put(
        route("executive.customers.update", props.customer.id),
        {
            name: form.name,
            email: form.email,
        },
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head :title="customer.name" />

    <ExecutiveLayout title="Customer">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div>
                <Link
                    :href="route('executive.customers.index')"
                    class="text-xs text-surface-500 hover:text-surface-900"
                >
                    ← Back to Customers
                </Link>

                <div
                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-3"
                >
                    <div>
                        <h1 class="text-xl font-semibold text-surface-900">
                            {{ customer.name }}
                        </h1>

                        <p class="text-sm text-surface-500 mt-1">
                            Customer details and communication.
                        </p>
                    </div>

                    <Link
                        :href="
                            route('executive.messages.show', {
                                customer: customer.id,
                            })
                        "
                        class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-900 transition"
                    >
                        Open WhatsApp Chat
                    </Link>
                </div>
            </div>

            <!-- Customer information -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main details -->
                <div
                    class="lg:col-span-2 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Customer Information
                        </h2>
                    </div>

                    <div class="p-6">
                        <form
                            @submit.prevent="submit"
                            class="grid grid-cols-1 md:grid-cols-2 gap-5"
                        >
                            <!-- Name -->
                            <div>
                                <label
                                    class="block text-xs font-medium text-surface-700 mb-1.5"
                                >
                                    Name
                                </label>

                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full rounded-lg border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                    required
                                />

                                <p
                                    v-if="form.errors.name"
                                    class="text-xs text-red-600 mt-1"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label
                                    class="block text-xs font-medium text-surface-700 mb-1.5"
                                >
                                    Email
                                </label>

                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="w-full rounded-lg border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />

                                <p
                                    v-if="form.errors.email"
                                    class="text-xs text-red-600 mt-1"
                                >
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <!-- Phone -->
                            <div>
                                <label
                                    class="block text-xs font-medium text-surface-700 mb-1.5"
                                >
                                    WhatsApp Number
                                </label>

                                <div
                                    class="w-full rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 text-sm text-surface-500"
                                >
                                    {{ maskedPhone(customer.phone) }}
                                </div>

                                <p class="text-[11px] text-surface-400 mt-1">
                                    Phone number is restricted for executives.
                                </p>
                            </div>

                            <!-- Status -->
                            <div>
                                <label
                                    class="block text-xs font-medium text-surface-700 mb-1.5"
                                >
                                    Status
                                </label>

                                <div
                                    class="w-full rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 text-sm text-surface-600 capitalize"
                                >
                                    {{ customer.status }}
                                </div>
                            </div>

                            <!-- Save -->
                            <div class="md:col-span-2 flex justify-end pt-2">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-5 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-900 disabled:opacity-50"
                                >
                                    {{
                                        form.processing
                                            ? "Saving..."
                                            : "Save Changes"
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Relationship -->
                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Customer Relationship
                        </h2>
                    </div>

                    <div class="p-6 space-y-5">
                        <!-- Assigned -->
                        <div>
                            <p
                                class="text-[11px] uppercase tracking-wide text-surface-400"
                            >
                                Assigned To
                            </p>

                            <p
                                v-if="customer.assigned_to"
                                class="text-sm font-medium text-surface-900 mt-1"
                            >
                                {{ customer.assigned_to.name }}
                            </p>

                            <p v-else class="text-sm text-surface-400 mt-1">
                                Unassigned
                            </p>
                        </div>

                        <!-- Previous owner -->
                        <div>
                            <p
                                class="text-[11px] uppercase tracking-wide text-surface-400"
                            >
                                Previous Owner
                            </p>

                            <p
                                v-if="customer.old_owner"
                                class="text-sm font-medium text-surface-900 mt-1"
                            >
                                {{ customer.old_owner.name }}
                            </p>

                            <p v-else class="text-sm text-surface-400 mt-1">
                                None
                            </p>
                        </div>

                        <!-- Team -->
                        <div>
                            <p
                                class="text-[11px] uppercase tracking-wide text-surface-400"
                            >
                                Team
                            </p>

                            <p
                                class="text-sm font-medium text-surface-900 mt-1"
                            >
                                {{ team.name }}
                            </p>
                        </div>

                        <!-- Created -->
                        <div>
                            <p
                                class="text-[11px] uppercase tracking-wide text-surface-400"
                            >
                                Created
                            </p>

                            <p class="text-sm text-surface-600 mt-1">
                                {{ formatDate(customer.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Communication -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div
                    class="px-6 py-4 border-b border-surface-200 flex items-center justify-between"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-surface-900">
                            WhatsApp Communication
                        </h2>

                        <p class="text-xs text-surface-500 mt-1">
                            Continue the conversation with this customer.
                        </p>
                    </div>

                    <Link
                        :href="
                            route('executive.messages.show', {
                                customer: customer.id,
                            })
                        "
                        class="text-xs font-medium text-slate-600 hover:text-slate-900"
                    >
                        Open Inbox →
                    </Link>
                </div>

                <div class="p-6">
                    <div
                        class="rounded-lg bg-surface-50 border border-surface-100 p-4"
                    >
                        <p class="text-sm text-surface-600">
                            Phone number is hidden from your account. You can
                            still communicate with this customer through the
                            WhatsApp inbox.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </ExecutiveLayout>
</template>