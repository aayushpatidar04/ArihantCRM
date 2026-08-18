<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

import TeamAdminLayout from "@/Components/Layout/TeamAdminLayout.vue";

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },

    executives: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: "",
    phone: "",
    email: "",
    assigned_to: null,
    notes: "",
    status: "active",
    tags: [],
});

const submit = () => {
    form.post(route("team-admin.customers.store"));
};
</script>

<template>
    <Head title="Add Customer" />

    <TeamAdminLayout title="Add Customer">
        <div class="max-w-3xl mx-auto space-y-6">
            <!-- Header -->
            <div>
                <Link
                    :href="route('team-admin.customers.index')"
                    class="text-xs text-surface-500 hover:text-surface-800"
                >
                    ← Back to Customers
                </Link>

                <h1 class="text-xl font-semibold text-surface-900 mt-3">
                    Add Customer
                </h1>

                <p class="text-sm text-surface-500 mt-1">
                    Customer will be created under
                    <strong>{{ team.name }}</strong
                    >.
                </p>
            </div>

            <!-- Form -->
            <form
                @submit.prevent="submit"
                class="bg-white border border-surface-200 rounded-xl shadow-sm p-6 space-y-5"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Name -->
                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Name
                        </label>

                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-lg border-surface-200 text-sm"
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
                        <label class="block text-xs font-medium mb-1">
                            Phone
                        </label>

                        <input
                            v-model="form.phone"
                            type="text"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        />

                        <p
                            v-if="form.errors.phone"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.phone }}
                        </p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Email
                        </label>

                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        />

                        <p
                            v-if="form.errors.email"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Assigned Executive -->
                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Assigned Executive
                        </label>

                        <select
                            v-model="form.assigned_to"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        >
                            <option :value="null">Unassigned</option>

                            <option
                                v-for="executive in executives"
                                :key="executive.id"
                                :value="executive.id"
                            >
                                {{ executive.name }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.assigned_to"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.assigned_to }}
                        </p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Status
                        </label>

                        <select
                            v-model="form.status"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        >
                            <option value="active">Active</option>

                            <option value="inactive">Inactive</option>

                            <option value="blocked">Blocked</option>
                        </select>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-medium mb-1">
                        Notes
                    </label>

                    <textarea
                        v-model="form.notes"
                        rows="4"
                        class="w-full rounded-lg border-surface-200 text-sm"
                    ></textarea>
                </div>

                <!-- Actions -->
                <div
                    class="pt-4 border-t border-surface-100 flex justify-end gap-3"
                >
                    <Link
                        :href="route('team-admin.customers.index')"
                        class="px-4 py-2 rounded-lg border border-surface-200 text-sm"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 rounded-lg bg-slate-600 text-white hover:bg-slate-900 text-sm font-medium disabled:opacity-50"
                    >
                        {{
                            form.processing ? "Creating..." : "Create Customer"
                        }}
                    </button>
                </div>
            </form>
        </div>
    </TeamAdminLayout>
</template>