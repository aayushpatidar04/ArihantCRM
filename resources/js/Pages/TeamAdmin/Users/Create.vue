<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

import TeamAdminLayout from "@/Components/Layout/TeamAdminLayout.vue";

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: "",
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
    role: "executive",
    is_active: true,
});

const submit = () => {
    form.post(route("team-admin.users.store"));
};
</script>

<template>
    <Head title="Add User" />

    <TeamAdminLayout title="Add User">
        <div class="max-w-3xl mx-auto space-y-6">
            <div>
                <Link
                    :href="route('team-admin.users.index')"
                    class="text-xs text-surface-500 hover:text-surface-800"
                >
                    ← Back to Users
                </Link>

                <h1 class="text-xl font-semibold text-surface-900 mt-3">
                    Add User
                </h1>

                <p class="text-sm text-surface-500 mt-1">
                    User will be created under
                    <strong>{{ team.name }}</strong
                    >.
                </p>
            </div>

            <form
                @submit.prevent="submit"
                class="bg-white border border-surface-200 rounded-xl shadow-sm p-6 space-y-5"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
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

                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Phone
                        </label>

                        <input
                            v-model="form.phone"
                            type="text"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Role
                        </label>

                        <select
                            v-model="form.role"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        >
                            <option value="executive">Executive</option>

                            <option value="auditor">Auditor</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Password
                        </label>

                        <input
                            v-model="form.password"
                            type="password"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1">
                            Confirm Password
                        </label>

                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            class="w-full rounded-lg border-surface-200 text-sm"
                        />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded border-surface-300"
                    />

                    Active
                </label>

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
                        {{ form.processing ? "Creating..." : "Create User" }}
                    </button>
                </div>
            </form>
        </div>
    </TeamAdminLayout>
</template>