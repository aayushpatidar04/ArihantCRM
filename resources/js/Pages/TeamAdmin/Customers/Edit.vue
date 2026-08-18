<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed } from "vue";

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
});

const form = useForm({
    name: props.customer.name ?? "",
    phone: props.customer.phone ?? "",
    email: props.customer.email ?? "",
    notes: props.customer.notes ?? "",
    status: props.customer.status ?? "active",
    tags: Array.isArray(props.customer.tags) ? props.customer.tags : [],
});

// computed string for the input
const tagsString = computed({
    get: () => form.tags.join(","),
    set: (val) => {
        form.tags = val.split(",").map(t => t.trim()).filter(Boolean);
    }
});

const tagsArray = computed(() =>
    form.tags
        ? form.tags.split(',').map(tag => tag.trim()).filter(tag => tag.length > 0)
        : []
);

const submit = () => {
    console.log(tagsArray.value);
    form.put(route("team-admin.customers.update", props.customer.id), {
        data: {
            ...form.data(),
            tags: tagsArray.value,
        },
    });
};
</script>

<template>
    <Head title="Edit Customer" />

    <TeamAdminLayout title="Edit Customer">
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
                    Edit Customer
                </h1>

                <p class="text-sm text-surface-500 mt-1">
                    Update customer information for
                    <strong>{{ props.customer.name }}</strong
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

                        <p
                            v-if="form.errors.status"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.status }}
                        </p>
                    </div>

                    <!-- Tags -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium mb-1">
                            Tags
                        </label>

                        <input
                            v-model="tagsString"
                            type="text"
                            placeholder="vip, follow-up, interested"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        />

                        <p class="text-[11px] text-surface-400 mt-1">
                            Separate multiple tags using commas.
                        </p>

                        <p
                            v-if="form.errors.tags"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.tags }}
                        </p>
                    </div>

                    <!-- Notes -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium mb-1">
                            Notes
                        </label>

                        <textarea
                            v-model="form.notes"
                            rows="5"
                            class="w-full rounded-lg border-surface-200 text-sm resize-none"
                            placeholder="Add notes about this customer..."
                        ></textarea>

                        <p
                            v-if="form.errors.notes"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.notes }}
                        </p>
                    </div>
                </div>

                <!-- Ownership information -->
                <div class="border-t border-surface-100 pt-5">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Ownership
                    </h2>

                    <p class="text-xs text-surface-500 mt-1">
                        Customer ownership is managed separately from basic
                        customer information.
                    </p>

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div
                            class="rounded-lg bg-surface-50 border border-surface-100 p-4"
                        >
                            <p
                                class="text-[11px] uppercase tracking-wide text-surface-400"
                            >
                                Current Executive
                            </p>

                            <p
                                class="text-sm font-medium text-surface-800 mt-1"
                            >
                                {{
                                    customer.assigned_to?.name || "Not assigned"
                                }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg bg-surface-50 border border-surface-100 p-4"
                        >
                            <p
                                class="text-[11px] uppercase tracking-wide text-surface-400"
                            >
                                Previous Owner
                            </p>

                            <p
                                class="text-sm font-medium text-surface-800 mt-1"
                            >
                                {{
                                    customer.old_owner?.name ||
                                    "No previous owner"
                                }}
                            </p>
                        </div>
                    </div>
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
                        {{ form.processing ? "Saving..." : "Save Changes" }}
                    </button>
                </div>
            </form>
        </div>
    </TeamAdminLayout>
</template>