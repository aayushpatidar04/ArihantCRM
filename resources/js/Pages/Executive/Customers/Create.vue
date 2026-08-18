<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

import ExecutiveLayout from "@/Components/Layout/ExecutiveLayout.vue";

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: "",
    phone: "",
    email: "",
});

const submit = () => {
    form.post(route("executive.customers.store"));
};
</script>

<template>
    <Head title="Add Customer" />

    <ExecutiveLayout title="Add Customer">
        <div class="max-w-2xl mx-auto space-y-6">
            <!-- Header -->
            <div>
                <Link
                    :href="route('executive.customers.index')"
                    class="text-xs text-surface-500 hover:text-surface-900"
                >
                    ← Back to Customers
                </Link>

                <h1 class="text-xl font-semibold text-surface-900 mt-3">
                    Add Customer
                </h1>

                <p class="text-sm text-surface-500 mt-1">
                    Add a new customer to {{ team.name }}.
                </p>
            </div>

            <!-- Form -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <form @submit.prevent="submit" class="p-6 space-y-5">
                    <!-- Name -->
                    <div>
                        <label
                            class="block text-xs font-medium text-surface-700 mb-1.5"
                        >
                            Customer Name
                        </label>

                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-lg border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                            placeholder="Enter customer name"
                            required
                        />

                        <p
                            v-if="form.errors.name"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label
                            class="block text-xs font-medium text-surface-700 mb-1.5"
                        >
                            WhatsApp Phone Number
                        </label>

                        <input
                            v-model="form.phone"
                            type="text"
                            inputmode="tel"
                            class="w-full rounded-lg border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                            placeholder="Enter WhatsApp number"
                            required
                        />

                        <p class="text-[11px] text-surface-400 mt-1">
                            This number is required for WhatsApp communication
                            but will be hidden from you after saving.
                        </p>

                        <p
                            v-if="form.errors.phone"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.phone }}
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
                            placeholder="customer@example.com"
                        />

                        <p
                            v-if="form.errors.email"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div
                        class="pt-4 border-t border-surface-100 flex items-center justify-end gap-3"
                    >
                        <Link
                            :href="route('executive.customers.index')"
                            class="px-4 py-2 rounded-lg border border-surface-200 text-sm text-surface-600 hover:bg-surface-50"
                        >
                            Cancel
                        </Link>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-900 disabled:opacity-50"
                        >
                            {{
                                form.processing
                                    ? "Creating..."
                                    : "Create Customer"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </ExecutiveLayout>
</template>