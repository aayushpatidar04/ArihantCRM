<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
});

const { success, error, info } = useToast();

function formatDate(dateString) {
    if (!dateString) return null;

    const date = new Date(dateString);

    return new Intl.DateTimeFormat("en-IN", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(date);
}

/*
|--------------------------------------------------------------------------
| Admin Management
|--------------------------------------------------------------------------
*/

const showAdminModal = ref(false);
const adminMode = ref("existing");

const availableAdmins = ref([]);
const loadingAdmins = ref(false);

const selectedAdminIds = ref([]);

const adminForm = ref({
    name: "",
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
});

const editingAdmin = ref(null);
const showEditModal = ref(false);

const editForm = ref({
    name: "",
    email: "",
    phone: "",
    is_active: true,
    password: "",
    password_confirmation: "",
});

const processing = ref(false);

const teamAdmins = computed(() => {
    return (props.team.accessible_users || []).filter((user) => {
        return user.roles?.some((role) => role.name === "team_admin");
    });
});

const executives = computed(() => {
    return (props.team.accessible_users || []).filter((user) => {
        return user.roles?.some((role) => role.name === "executive");
    });
});

const auditors = computed(() => {
    return (props.team.accessible_users || []).filter((user) => {
        return user.roles?.some((role) => role.name === "auditor");
    });
});

function openAdminModal() {
    adminMode.value = "existing";
    selectedAdminIds.value = [];

    adminForm.value = {
        name: "",
        email: "",
        phone: "",
        password: "",
        password_confirmation: "",
    };

    showAdminModal.value = true;

    loadAvailableAdmins();
}

function closeAdminModal() {
    showAdminModal.value = false;
}

async function loadAvailableAdmins() {
    loadingAdmins.value = true;

    try {
        const response = await fetch(
            route("superadmin.teams.available-admins", props.team.id),
            {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            },
        );

        if (!response.ok) {
            throw new Error("Unable to load team admins.");
        }

        availableAdmins.value = await response.json();

        /*
         * Automatically select admins who already belong
         * to this team.
         */
        selectedAdminIds.value = availableAdmins.value
            .filter((admin) => admin.checked)
            .map((admin) => admin.id);
    } catch (error) {
        console.error(error);
    } finally {
        loadingAdmins.value = false;
    }
}

function toggleAdmin(adminId) {
    if (selectedAdminIds.value.includes(adminId)) {
        selectedAdminIds.value = selectedAdminIds.value.filter(
            (id) => id !== adminId,
        );
    } else {
        selectedAdminIds.value.push(adminId);
    }
}

function saveExistingAdmins() {
    if (!selectedAdminIds.value.length) {
        return;
    }

    processing.value = true;

    router.post(
        route("superadmin.teams.admins.store", props.team.id),
        {
            admin_ids: selectedAdminIds.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                showAdminModal.value = false;
            },
        },
    );
}

function createAdmin() {
    processing.value = true;

    router.post(
        route("superadmin.teams.admins.store", props.team.id),
        adminForm.value,
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                showAdminModal.value = false;
            },
        },
    );
}

/*
|--------------------------------------------------------------------------
| Edit Admin
|--------------------------------------------------------------------------
*/

function openEditAdmin(admin) {
    editingAdmin.value = admin;

    editForm.value = {
        name: admin.name || "",
        email: admin.email || "",
        phone: admin.phone || "",
        is_active: admin.is_active ?? true,
        password: "",
        password_confirmation: "",
    };

    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
    editingAdmin.value = null;
}

function updateAdmin() {
    if (!editingAdmin.value) return;

    processing.value = true;

    router.put(
        route("superadmin.teams.admins.update", {
            team: props.team.id,
            user: editingAdmin.value.id,
        }),
        editForm.value,
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                closeEditModal();
            },
        },
    );
}

/*
|--------------------------------------------------------------------------
| Remove Admin From Team
|--------------------------------------------------------------------------
*/

function removeAdmin(admin) {
    if (
        !confirm(
            `Remove ${admin.name} from ${props.team.name}?\n\nThe user will not be deleted. They will only lose access to this team.`,
        )
    ) {
        return;
    }

    router.delete(
        route("superadmin.teams.admins.destroy", {
            team: props.team.id,
            user: admin.id,
        }),
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head :title="team.name" />

    <SuperAdminLayout :title="team.name">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold text-surface-900">
                            {{ team.name }}
                        </h1>

                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                            :class="
                                team.is_active
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-surface-100 text-surface-600'
                            "
                        >
                            {{ team.is_active ? "Active" : "Inactive" }}
                        </span>
                    </div>

                    <p class="mt-1 text-sm text-surface-500">
                        Team details and platform configuration.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('superadmin.teams.index')"
                        class="px-4 py-2 text-sm font-medium text-surface-700 bg-white border border-surface-200 rounded-lg hover:bg-surface-50 hover:border-slate-500 transition"
                    >
                        ← Back
                    </Link>

                    <Link
                        :href="route('superadmin.teams.edit', team.id)"
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 transition"
                    >
                        Edit Team
                    </Link>
                </div>
            </div>

            <!-- Main grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Team Information -->
                <div
                    class="lg:col-span-2 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Team Information
                        </h2>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Team Name
                            </p>

                            <p class="text-sm font-medium text-surface-900">
                                {{ team.name || "—" }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500 mb-1">Slug</p>

                            <p class="text-sm font-medium text-surface-900">
                                {{ team.slug || "—" }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Parent Team
                            </p>

                            <Link
                                v-if="team.parent_team"
                                :href="
                                    route(
                                        'superadmin.teams.show',
                                        team.parent_team.id,
                                    )
                                "
                                class="text-sm font-medium text-slate-600 hover:text-slate-700"
                            >
                                {{ team.parent_team.name }}
                            </Link>

                            <p
                                v-else
                                class="text-sm font-medium text-surface-900"
                            >
                                No Parent Team
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Bitrix Department ID
                            </p>

                            <p class="text-sm font-medium text-surface-900">
                                {{ team.external_department_id || "—" }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500 mb-1">Created</p>

                            <p class="text-sm font-medium text-surface-900">
                                {{ formatDate(team.created_at) || "—" }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Last Updated
                            </p>

                            <p class="text-sm font-medium text-surface-900">
                                {{ formatDate(team.updated_at) || "—" }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm h-fit"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Status
                        </h2>
                    </div>

                    <div class="p-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-full flex items-center justify-center"
                                :class="
                                    team.is_active
                                        ? 'bg-emerald-50'
                                        : 'bg-surface-100'
                                "
                            >
                                <svg
                                    v-if="team.is_active"
                                    class="w-5 h-5 text-emerald-600"
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

                                <svg
                                    v-else
                                    class="w-5 h-5 text-surface-500"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-surface-900">
                                    {{ team.is_active ? "Active" : "Inactive" }}
                                </p>

                                <p class="text-xs text-surface-500 mt-0.5">
                                    {{
                                        team.is_active
                                            ? "This team is currently active."
                                            : "This team is currently inactive."
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Number -->
            <div>
                <div class="px-6 py-4">
                    <p class="text-xs font-medium text-surface-500 mb-3">
                        WhatsApp Number
                    </p>

                    <!-- Assigned -->
                    <div
                        v-if="team.whatsapp_number"
                        class="flex items-center gap-3"
                    >
                        <div
                            class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center"
                        >
                            <svg
                                class="w-5 h-5 text-emerald-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a2 2 0 011.8 1.1l1.42 2.84a2 2 0 01-.45 2.31l-1.7 1.7a16.02 16.02 0 006.7 6.7l1.7-1.7a2 2 0 012.31-.45l2.84 1.42A2 2 0 0123 18.72V22a2 2 0 01-2 2h-1C10.72 24 0 13.28 0 0V-1a2 2 0 012-2h3z"
                                />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-medium text-surface-900">
                                {{
                                    team.whatsapp_number.display_phone_number ||
                                    team.whatsapp_number.phone_number ||
                                    "—"
                                }}
                            </p>

                            <p
                                v-if="team.whatsapp_number.verified_name"
                                class="text-xs text-surface-500 mt-0.5"
                            >
                                {{ team.whatsapp_number.verified_name }}
                            </p>

                            <p class="text-xs mt-1">
                                <span
                                    :class="
                                        team.whatsapp_number.is_active
                                            ? 'text-emerald-600'
                                            : 'text-surface-500'
                                    "
                                >
                                    {{
                                        team.whatsapp_number.is_active
                                            ? "Active"
                                            : "Inactive"
                                    }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Not assigned -->
                    <div v-else class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-full bg-surface-100 flex items-center justify-center"
                        >
                            <svg
                                class="w-5 h-5 text-surface-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                                />
                            </svg>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-surface-900">
                                No WhatsApp Number
                            </p>

                            <p class="text-xs text-surface-500 mt-0.5">
                                No WhatsApp number is assigned to this team.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- TEAM ADMINS -->
            <!-- ========================================================= -->

            <div
                class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div
                    class="px-6 py-4 border-b border-surface-200 flex items-center justify-between"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-surface-900">
                            Team Admins
                        </h2>

                        <p class="text-xs text-surface-500 mt-1">
                            Team admins can manage this team. An admin can
                            manage multiple teams.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="openAdminModal"
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 transition"
                    >
                        + Add Admin
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table v-if="teamAdmins.length" class="w-full text-sm">
                        <thead
                            class="bg-surface-50 border-b border-surface-200"
                        >
                            <tr>
                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Name
                                </th>

                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Email
                                </th>

                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Phone
                                </th>

                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Status
                                </th>

                                <th
                                    class="text-right px-6 py-3 font-medium text-surface-500"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-100">
                            <tr
                                v-for="admin in teamAdmins"
                                :key="admin.id"
                                class="hover:bg-surface-50"
                            >
                                <td class="px-6 py-4">
                                    <p class="font-medium text-surface-900">
                                        {{ admin.name }}
                                    </p>
                                </td>

                                <td class="px-6 py-4 text-surface-600">
                                    {{ admin.email }}
                                </td>

                                <td class="px-6 py-4 text-surface-600">
                                    {{ admin.phone || "—" }}
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex px-2 py-1 rounded-full text-xs font-medium"
                                        :class="
                                            admin.is_active
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-red-50 text-red-700'
                                        "
                                    >
                                        {{
                                            admin.is_active
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <button
                                            type="button"
                                            @click="openEditAdmin(admin)"
                                            class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-50 rounded-lg hover:bg-slate-100"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            @click="removeAdmin(admin)"
                                            class="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="px-6 py-10 text-center">
                        <p class="text-sm text-surface-500">
                            No team admin has been assigned yet.
                        </p>

                        <button
                            type="button"
                            @click="openAdminModal"
                            class="mt-3 text-sm font-medium text-slate-600 hover:text-slate-700"
                        >
                            Add the first team admin
                        </button>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- ALL TEAM USERS -->
            <!-- ========================================================= -->

            <div
                class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Team Users
                    </h2>

                    <p class="text-xs text-surface-500 mt-1">
                        Users currently having access to this team.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table v-if="team.accessible_users?.length" class="w-full text-sm">
                        <thead
                            class="bg-surface-50 border-b border-surface-200"
                        >
                            <tr>
                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Name
                                </th>

                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Email
                                </th>

                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Role
                                </th>

                                <th
                                    class="text-left px-6 py-3 font-medium text-surface-500"
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-surface-100">
                            <tr
                                v-for="user in team.accessible_users"
                                :key="user.id"
                                class="hover:bg-surface-50"
                            >
                                <td class="px-6 py-4">
                                    <p class="font-medium text-surface-900">
                                        {{ user.name }}
                                    </p>
                                </td>

                                <td class="px-6 py-4 text-surface-600">
                                    {{ user.email }}
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="inline-flex px-2 py-1 mr-1 rounded-full text-xs font-medium bg-surface-100 text-surface-700"
                                    >
                                        {{ role.name }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex px-2 py-1 rounded-full text-xs font-medium"
                                        :class="
                                            user.is_active
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-red-50 text-red-700'
                                        "
                                    >
                                        {{
                                            user.is_active
                                                ? "Active"
                                                : "Inactive"
                                        }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-else
                        class="px-6 py-10 text-center text-sm text-surface-500"
                    >
                        No users have access to this team.
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- PLATFORM INFORMATION -->
            <!-- ========================================================= -->

            <div
                class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Platform Information
                    </h2>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-xs text-surface-500 mb-1">Team ID</p>

                        <p class="text-sm font-medium text-surface-900">
                            #{{ team.id }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Parent Team ID
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ team.parent_team_id || "—" }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            External Department ID
                        </p>

                        <p class="text-sm font-medium text-surface-900">
                            {{ team.external_department_id || "—" }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- ADD ADMIN MODAL -->
        <!-- ============================================================= -->

        <div
            v-if="showAdminModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <div
                class="absolute inset-0 bg-black/40"
                @click="closeAdminModal"
            />

            <div
                class="relative w-full max-w-xl bg-white rounded-2xl shadow-xl overflow-hidden"
            >
                <!-- Modal Header -->
                <div
                    class="px-6 py-4 border-b border-surface-200 flex items-center justify-between"
                >
                    <div>
                        <h3 class="text-base font-semibold text-surface-900">
                            Add Team Admin
                        </h3>

                        <p class="text-xs text-surface-500 mt-1">
                            Assign an existing team admin or create a new one.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="closeAdminModal"
                        class="text-surface-400 hover:text-surface-700 text-xl"
                    >
                        ×
                    </button>
                </div>

                <!-- Tabs -->
                <div class="px-6 pt-4">
                    <div class="flex border-b border-surface-200">
                        <button
                            type="button"
                            @click="adminMode = 'existing'"
                            class="px-4 py-2 text-sm font-medium border-b-2"
                            :class="
                                adminMode === 'existing'
                                    ? 'border-slate-600 text-slate-600'
                                    : 'border-transparent text-surface-500'
                            "
                        >
                            Existing Admin
                        </button>

                        <button
                            type="button"
                            @click="adminMode = 'new'"
                            class="px-4 py-2 text-sm font-medium border-b-2"
                            :class="
                                adminMode === 'new'
                                    ? 'border-slate-600 text-slate-600'
                                    : 'border-transparent text-surface-500'
                            "
                        >
                            Create New
                        </button>
                    </div>
                </div>

                <!-- Existing Admin -->
                <div v-if="adminMode === 'existing'" class="p-6">
                    <div
                        v-if="loadingAdmins"
                        class="py-8 text-center text-sm text-surface-500"
                    >
                        Loading team admins...
                    </div>

                    <div
                        v-else-if="!availableAdmins.length"
                        class="py-8 text-center"
                    >
                        <p class="text-sm text-surface-500">
                            No team admins are available.
                        </p>

                        <button
                            type="button"
                            @click="adminMode = 'new'"
                            class="mt-2 text-sm text-slate-600 font-medium"
                        >
                            Create a new team admin
                        </button>
                    </div>

                    <div v-else class="space-y-2 max-h-72 overflow-y-auto">
                        <button
                            v-for="admin in availableAdmins"
                            :key="admin.id"
                            type="button"
                            @click="toggleAdmin(admin.id)"
                            class="w-full flex items-center gap-3 p-3 rounded-xl border text-left transition"
                            :class="
                                selectedAdminIds.includes(admin.id)
                                    ? 'border-slate-300 bg-slate-50'
                                    : 'border-surface-200 hover:bg-surface-50'
                            "
                        >
                            <div
                                class="w-9 h-9 rounded-full bg-surface-100 flex items-center justify-center text-xs font-semibold text-surface-600"
                            >
                                {{ admin.name?.charAt(0)?.toUpperCase() }}
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-surface-900">
                                    {{ admin.name }}
                                </p>

                                <p class="text-xs text-surface-500 truncate">
                                    {{ admin.email }}
                                </p>
                            </div>

                            <div
                                class="w-5 h-5 rounded border flex items-center justify-center"
                                :class="
                                    selectedAdminIds.includes(admin.id)
                                        ? 'bg-slate-600 border-slate-600 text-white'
                                        : 'border-surface-300'
                                "
                            >
                                <span
                                    v-if="selectedAdminIds.includes(admin.id)"
                                    class="text-xs"
                                >
                                    ✓
                                </span>
                            </div>
                        </button>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="closeAdminModal"
                            class="px-4 py-2 text-sm border border-slate-500 rounded-lg"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            @click="saveExistingAdmins"
                            :disabled="processing || !selectedAdminIds.length"
                            class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-lg disabled:opacity-50"
                        >
                            {{ processing ? "Saving..." : "Assign Admin" }}
                        </button>
                    </div>
                </div>

                <!-- Create New Admin -->
                <div v-else class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-1"
                            >
                                Name
                            </label>

                            <input
                                v-model="adminForm.name"
                                type="text"
                                class="w-full rounded-lg border border-surface-200 text-sm"
                                placeholder="Admin name"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-1"
                            >
                                Email
                            </label>

                            <input
                                v-model="adminForm.email"
                                type="email"
                                class="w-full rounded-lg border border-surface-200 text-sm"
                                placeholder="admin@example.com"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-1"
                            >
                                Phone
                            </label>

                            <input
                                v-model="adminForm.phone"
                                type="text"
                                class="w-full rounded-lg border border-surface-200 text-sm"
                                placeholder="Phone number"
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-xs font-medium text-surface-700 mb-1"
                                >
                                    Password
                                </label>

                                <input
                                    v-model="adminForm.password"
                                    type="password"
                                    class="w-full rounded-lg border border-surface-200 text-sm"
                                />
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-medium text-surface-700 mb-1"
                                >
                                    Confirm Password
                                </label>

                                <input
                                    v-model="adminForm.password_confirmation"
                                    type="password"
                                    class="w-full rounded-lg border border-surface-200 text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="closeAdminModal"
                            class="px-4 py-2 text-sm border border-slate-500 rounded-lg"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            @click="createAdmin"
                            :disabled="processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-lg disabled:opacity-50"
                        >
                            {{ processing ? "Creating..." : "Create Admin" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- EDIT ADMIN MODAL -->
        <!-- ============================================================= -->

        <div
            v-if="showEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <div class="absolute inset-0 bg-black/40" @click="closeEditModal" />

            <div
                class="relative w-full max-w-lg bg-white rounded-2xl shadow-xl overflow-hidden"
            >
                <div
                    class="px-6 py-4 border-b border-surface-200 flex items-center justify-between"
                >
                    <div>
                        <h3 class="text-base font-semibold text-surface-900">
                            Edit Team Admin
                        </h3>

                        <p class="text-xs text-surface-500 mt-1">
                            Update this admin's account details.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="closeEditModal"
                        class="text-surface-400 hover:text-surface-700 text-xl"
                    >
                        ×
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label
                            class="block text-xs font-medium text-surface-700 mb-1"
                        >
                            Name
                        </label>

                        <input
                            v-model="editForm.name"
                            type="text"
                            class="w-full rounded-lg border border-surface-200 text-sm"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-xs font-medium text-surface-700 mb-1"
                        >
                            Email
                        </label>

                        <input
                            v-model="editForm.email"
                            type="email"
                            class="w-full rounded-lg border border-surface-200 text-sm"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-xs font-medium text-surface-700 mb-1"
                        >
                            Phone
                        </label>

                        <input
                            v-model="editForm.phone"
                            type="text"
                            class="w-full rounded-lg border border-surface-200 text-sm"
                        />
                    </div>

                    <div>
                        <label
                            class="flex items-center gap-2 text-sm text-surface-700"
                        >
                            <input
                                v-model="editForm.is_active"
                                type="checkbox"
                                class="rounded border-surface-300"
                            />

                            Active
                        </label>
                    </div>

                    <div class="pt-2 border-t border-surface-100">
                        <p class="text-xs text-surface-500 mb-3">
                            Leave password blank if you don't want to change it.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-xs font-medium text-surface-700 mb-1"
                                >
                                    New Password
                                </label>

                                <input
                                    v-model="editForm.password"
                                    type="password"
                                    class="w-full rounded-lg border border-surface-200 text-sm"
                                />
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-medium text-surface-700 mb-1"
                                >
                                    Confirm Password
                                </label>

                                <input
                                    v-model="editForm.password_confirmation"
                                    type="password"
                                    class="w-full rounded-lg border border-surface-200 text-sm"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="px-6 py-4 bg-surface-50 border-t border-surface-200 flex justify-end gap-2"
                >
                    <button
                        type="button"
                        @click="closeEditModal"
                        class="px-4 py-2 text-sm border border-surface-200 bg-white rounded-lg"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        @click="updateAdmin"
                        :disabled="processing"
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-lg disabled:opacity-50"
                    >
                        {{ processing ? "Saving..." : "Save Changes" }}
                    </button>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
