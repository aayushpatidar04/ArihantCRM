<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },

    teams: {
        type: Array,
        default: () => [],
    },

    whatsappNumbers: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: props.team.name ?? "",
    slug: props.team.slug ?? "",
    parent_team_id: props.team.parent_team_id ?? "",
    external_department_id: props.team.external_department_id ?? "",
    whatsapp_number_id: props.team.whatsapp_number_id ?? "",
    is_active: Boolean(props.team.is_active),
});

const submit = () => {
    form.put(route("superadmin.teams.update", props.team.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Edit ${team.name}`" />

    <SuperAdminLayout title="Edit Team">
        <div class="p-6 max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        Edit Team
                    </h1>

                    <p class="mt-1 text-sm text-surface-500">
                        Update team/company information.
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
                            :class="{
                                'border-red-500': form.errors.name,
                            }"
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
                            type="text"
                            placeholder="team-slug"
                            class="w-full rounded-lg border border-surface-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:ring-slate-500"
                            :class="{
                                'border-red-500': form.errors.slug,
                            }"
                        />

                        <p class="mt-1 text-xs text-surface-500">
                            Must be unique.
                        </p>

                        <p
                            v-if="form.errors.slug"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.slug }}
                        </p>
                    </div>

                    <!-- WhatsApp Number -->
                    <div>
                        <label
                            for="whatsapp_number_id"
                            class="block text-sm font-medium text-surface-700 mb-1.5"
                        >
                            WhatsApp Number
                        </label>

                        <select
                            id="whatsapp_number_id"
                            v-model="form.whatsapp_number_id"
                            class="w-full rounded-lg border border-surface-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:ring-slate-500"
                            :class="{
                                'border-red-500':
                                    form.errors.whatsapp_number_id,
                            }"
                        >
                            <option value="">No WhatsApp Number</option>

                            <option
                                v-for="number in whatsappNumbers"
                                :key="number.id"
                                :value="number.id"
                            >
                                {{
                                    number.display_phone_number ||
                                    number.phone_number
                                }}
                                <template v-if="number.verified_name">
                                    — {{ number.verified_name }}
                                </template>
                            </option>
                        </select>

                        <p class="mt-1.5 text-xs text-surface-500">
                            This team can use the selected WhatsApp number for
                            customer communication.
                        </p>

                        <p
                            v-if="form.errors.whatsapp_number_id"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.whatsapp_number_id }}
                        </p>

                        <!-- Current selection -->
                        <div
                            v-if="form.whatsapp_number_id"
                            class="mt-3 flex items-center gap-3 rounded-lg border border-surface-200 bg-surface-50 px-4 py-3"
                        >
                            <div
                                class="w-9 h-9 rounded-full bg-emerald-50 flex items-center justify-center"
                            >
                                <svg
                                    class="w-4 h-4 text-emerald-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="text-xs text-surface-500">
                                    Selected WhatsApp Number
                                </p>

                                <p class="text-sm font-medium text-surface-900">
                                    {{
                                        whatsappNumbers.find(
                                            (number) =>
                                                String(number.id) ===
                                                String(form.whatsapp_number_id),
                                        )?.display_phone_number ||
                                        whatsappNumbers.find(
                                            (number) =>
                                                String(number.id) ===
                                                String(form.whatsapp_number_id),
                                        )?.phone_number ||
                                        "Selected number"
                                    }}
                                </p>
                            </div>
                        </div>
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
                                v-for="parentTeam in teams"
                                :key="parentTeam.id"
                                :value="parentTeam.id"
                                :disabled="parentTeam.id === team.id"
                            >
                                {{ parentTeam.name }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.parent_team_id"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.parent_team_id }}
                        </p>
                    </div>

                    <!-- Bitrix Department -->
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
                        {{ form.processing ? "Saving..." : "Save Changes" }}
                    </button>
                </div>
            </form>
        </div>
    </SuperAdminLayout>
</template>
