<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

import TeamAdminLayout from "@/Components/Layout/TeamAdminLayout.vue";

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },

    team: {
        type: Object,
        required: true,
    },

    roles: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: props.user.name ?? "",
    email: props.user.email ?? "",
    phone: props.user.phone ?? "",
    password: "",
    password_confirmation: "",
    role: props.user.roles?.[0] ?? props.roles[0] ?? "executive",
    is_active: props.user.is_active ?? true,
});

const submit = () => {
    form.put(route("team-admin.users.update", props.user.id));
};
</script>

<template>
    <Head title="Edit User" />

    <TeamAdminLayout title="Edit User">
        <div class="max-w-3xl mx-auto space-y-6">
            <!-- Header -->
            <div>
                <Link
                    :href="route('team-admin.users.index')"
                    class="text-xs text-surface-500 hover:text-surface-800"
                >
                    ← Back to Users
                </Link>

                <h1 class="text-xl font-semibold text-surface-900 mt-3">
                    Edit User
                </h1>

                <p class="text-sm text-surface-500 mt-1">
                    Update
                    <strong>{{ user.name }}</strong>
                    in
                    <strong>{{ team.name }}</strong>.
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

                    <!-- Role -->
                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Role
                        </label>

                        <select
                            v-model="form.role"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        >
                            <option
                                v-for="role in roles"
                                :key="role"
                                :value="role"
                            >
                                {{
                                    role
                                        .replace(/_/g, " ")
                                        .replace(/\b\w/g, (c) =>
                                            c.toUpperCase(),
                                        )
                                }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.role"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.role }}
                        </p>
                    </div>

                    <!-- New Password -->
                    <div>
                        <label class="block text-xs font-medium mb-1">
                            New Password
                        </label>

                        <input
                            v-model="form.password"
                            type="password"
                            class="w-full rounded-lg border-surface-200 text-sm"
                            placeholder="Leave blank to keep current"
                        />

                        <p
                            v-if="form.errors.password"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Confirm Password
                        </label>

                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            class="w-full rounded-lg border-surface-200 text-sm"
                            placeholder="Leave blank to keep current"
                        />
                    </div>
                </div>

                <!-- Password Information -->
                <div
                    class="rounded-lg bg-surface-50 border border-surface-100 px-4 py-3"
                >
                    <p class="text-xs text-surface-500">
                        Leave the password fields empty if you don't want to
                        change the user's current password.
                    </p>
                </div>

                <!-- Status -->
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded border-surface-300"
                    />

                    Active
                </label>

                <p
                    v-if="form.errors.is_active"
                    class="text-xs text-red-600 -mt-3"
                >
                    {{ form.errors.is_active }}
                </p>

                <!-- Actions -->
                <div
                    class="pt-4 border-t border-surface-100 flex justify-end gap-3"
                >
                    <Link
                        :href="route('team-admin.users.index')"
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