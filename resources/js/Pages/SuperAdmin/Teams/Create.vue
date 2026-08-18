<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";

const props = defineProps({
    teams: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: "",
    slug: "",
    parent_team_id: "",
    external_department_id: "",
    is_active: true,
});

const slugManuallyEdited = ref(false);

const generateSlug = (value) => {
    return value
        .toString()
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, "")
        .replace(/\s+/g, "-")
        .replace(/-+/g, "-");
};

watch(
    () => form.name,
    (value) => {
        if (!slugManuallyEdited.value) {
            form.slug = generateSlug(value);
        }
    },
);

const submit = () => {
    form.post(route("superadmin.teams.store"), {
        preserveScroll: true,
    });
};

const parentTeams = computed(() => props.teams);
</script>

<template>
    <Head title="Create Team" />

    <SuperAdminLayout title="Create Team">
        <div class="p-6 max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        Create Team
                    </h1>

                    <p class="mt-1 text-sm text-surface-500">
                        Create a new team/company on the platform.
                    </p>
                </div>

                <Link
                    :href="route('superadmin.teams.index')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-surface-700 bg-white border border-surface-200 rounded-lg hover:bg-surface-50 transition"
                >
                    ← Back
                </Link>
            </div>

            <!-- Form -->
            <form
                @submit.prevent="submit"
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="p-6 space-y-6">
                    <!-- Team Name -->
                    <div>
                        <label
                            for="name"
                            class="block text-sm font-medium text-surface-700 mb-1.5"
                        >
                            Team Name
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Enter team name"
                            class="w-full rounded-lg border border-surface-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:ring-slate-500"
                            :class="{ 'border-red-500': form.errors.name }"
                        />

                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Slug -->
                    <div>
                        <label
                            for="slug"
                            class="block text-sm font-medium text-surface-700 mb-1.5"
                        >
                            Slug
                        </label>

                        <input
                            id="slug"
                            v-model="form.slug"
                            @input="slugManuallyEdited = true"
                            type="text"
                            placeholder="team-slug"
                            class="w-full rounded-lg border border-surface-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:ring-slate-500"
                            :class="{ 'border-red-500': form.errors.slug }"
                        />

                        <p class="mt-1 text-xs text-surface-500">
                            Used as the unique identifier for this team.
                        </p>

                        <p
                            v-if="form.errors.slug"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.slug }}
                        </p>
                    </div>

                    <!-- Parent Team -->
                    <div>
                        <label
                            for="parent_team_id"
                            class="block text-sm font-medium text-surface-700 mb-1.5"
                        >
                            Parent Team
                        </label>

                        <select
                            id="parent_team_id"
                            v-model="form.parent_team_id"
                            class="w-full rounded-lg border border-surface-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:ring-slate-500"
                            :class="{
                                'border-red-500': form.errors.parent_team_id,
                            }"
                        >
                            <option value="">No Parent Team</option>

                            <option
                                v-for="team in parentTeams"
                                :key="team.id"
                                :value="team.id"
                            >
                                {{ team.name }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.parent_team_id"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.parent_team_id }}
                        </p>
                    </div>

                    <!-- External Department ID -->
                    <div>
                        <label
                            for="external_department_id"
                            class="block text-sm font-medium text-surface-700 mb-1.5"
                        >
                            Bitrix Department ID
                        </label>

                        <input
                            id="external_department_id"
                            v-model="form.external_department_id"
                            type="text"
                            placeholder="Optional"
                            class="w-full rounded-lg border border-surface-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:ring-slate-500"
                            :class="{
                                'border-red-500':
                                    form.errors.external_department_id,
                            }"
                        />

                        <p
                            v-if="form.errors.external_department_id"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.external_department_id }}
                        </p>
                    </div>

                    <!-- Status -->
                    <div
                        class="flex items-center justify-between rounded-lg border border-surface-200 bg-surface-50 px-4 py-3"
                    >
                        <div>
                            <p class="text-sm font-medium text-surface-800">
                                Active Team
                            </p>

                            <p class="text-xs text-surface-500 mt-0.5">
                                Inactive teams won't be available for normal
                                operations.
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="form.is_active = !form.is_active"
                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors focus:outline-none"
                            :class="
                                form.is_active
                                    ? 'bg-slate-600'
                                    : 'bg-surface-300 border border-slate-500'
                            "
                        >
                            <span
                                class="inline-block h-5 w-5 mt-0.5 rounded-full shadow transform transition-transform"
                                :class="
                                    form.is_active
                                        ? 'translate-x-5 bg-white'
                                        : 'translate-x-0.5 bg-slate-500'
                                "
                            />
                        </button>
                    </div>

                    <p
                        v-if="form.errors.is_active"
                        class="text-xs text-red-600"
                    >
                        {{ form.errors.is_active }}
                    </p>
                </div>

                <!-- Footer -->
                <div
                    class="px-6 py-4 bg-surface-50 border-t border-surface-200 flex items-center justify-end gap-3 rounded-b-xl"
                >
                    <Link
                        :href="route('superadmin.teams.index')"
                        class="px-4 py-2 text-sm font-medium text-surface-700 hover:bg-white border border-transparent hover:border-slate-500 rounded-lg transition"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2 text-sm font-medium text-white bg-slate-600 hover:bg-slate-900 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        {{ form.processing ? "Creating..." : "Create Team" }}
                    </button>
                </div>
            </form>
        </div>
    </SuperAdminLayout>
</template>