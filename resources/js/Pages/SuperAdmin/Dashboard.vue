<template>
    <SuperAdminLayout title="Platform Overview">
        <template #header>
            <div>
                <h1 class="text-base font-semibold text-surface-900">
                    Platform Overview
                </h1>

                <p class="text-xs text-surface-400 mt-0.5">
                    Manage your WhatsApp CRM platform
                </p>
            </div>
        </template>

        <div class="p-6 space-y-6 animate-fade-in">
            <!-- ─────────────────────────────────────────────
                 Stats
            ───────────────────────────────────────────── -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl border border-surface-100 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-surface-400">
                                Total Teams
                            </p>

                            <p
                                class="text-2xl font-semibold text-surface-900 mt-1"
                            >
                                {{ stats.total_teams }}
                            </p>
                        </div>

                        <div
                            class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center"
                        >
                            🏢
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-surface-100 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-surface-400">
                                Active Teams
                            </p>

                            <p
                                class="text-2xl font-semibold text-surface-900 mt-1"
                            >
                                {{ stats.active_teams }}
                            </p>
                        </div>

                        <div
                            class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center"
                        >
                            ✓
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-surface-100 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-surface-400">Total Users</p>

                            <p
                                class="text-2xl font-semibold text-surface-900 mt-1"
                            >
                                {{ stats.total_users }}
                            </p>
                        </div>

                        <div
                            class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center"
                        >
                            👥
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-surface-100 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-surface-400">
                                WhatsApp Numbers
                            </p>

                            <p
                                class="text-2xl font-semibold text-surface-900 mt-1"
                            >
                                {{ stats.total_whatsapp_numbers ?? 0 }}
                            </p>
                        </div>

                        <div
                            class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center"
                        >
                            💬
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─────────────────────────────────────────────
                 Quick Actions
            ───────────────────────────────────────────── -->
            <div>
                <h2 class="text-sm font-semibold text-surface-700 mb-3">
                    Quick Actions
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Link
                        :href="route('superadmin.teams.index')"
                        class="group bg-white border border-surface-100 rounded-2xl p-5 hover:border-brand-200 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg"
                            >
                                🏢
                            </div>

                            <div>
                                <p
                                    class="text-sm font-semibold text-surface-800 group-hover:text-brand-600"
                                >
                                    Manage Teams
                                </p>

                                <p class="text-xs text-surface-400 mt-0.5">
                                    Create and manage teams
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="route('superadmin.whatsapp-templates.index')"
                        class="group bg-white border border-surface-100 rounded-2xl p-5 hover:border-brand-200 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg"
                            >
                                📝
                            </div>

                            <div>
                                <p
                                    class="text-sm font-semibold text-surface-800 group-hover:text-brand-600"
                                >
                                    Manage Templates
                                </p>

                                <p class="text-xs text-surface-400 mt-0.5">
                                    WhatsApp message templates
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="route('superadmin.whatsapp-numbers.index')"
                        class="group bg-white border border-surface-100 rounded-2xl p-5 hover:border-brand-200 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg"
                            >
                                📱
                            </div>

                            <div>
                                <p
                                    class="text-sm font-semibold text-surface-800"
                                >
                                    WhatsApp Infrastructure
                                </p>

                                <p class="text-xs text-surface-400 mt-0.5">
                                    {{ stats.total_whatsapp_numbers ?? 0 }}
                                    configured numbers
                                </p>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- ─────────────────────────────────────────────
                 Teams
            ───────────────────────────────────────────── -->
            <div
                class="bg-white rounded-2xl border border-surface-100 overflow-hidden"
            >
                <div
                    class="px-5 py-4 border-b border-surface-100 flex items-center justify-between"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-surface-700">
                            Teams
                        </h2>

                        <p class="text-xs text-surface-400 mt-0.5">
                            Recently created teams
                        </p>
                    </div>

                    <Link
                        :href="route('superadmin.teams.index')"
                        class="text-xs font-medium text-brand-600 hover:text-brand-700"
                    >
                        View all →
                    </Link>
                </div>

                <div
                    v-if="teams.length"
                    class="divide-y divide-surface-100"
                >
                    <div
                        v-for="team in teams"
                        :key="team.id"
                        class="px-5 py-4 flex items-center gap-4 hover:bg-surface-50 transition-colors"
                    >
                        <!-- Team icon -->
                        <div
                            class="w-10 h-10 rounded-xl bg-surface-100 flex items-center justify-center text-sm font-semibold text-surface-500 shrink-0"
                        >
                            {{ team.name?.charAt(0)?.toUpperCase() }}
                        </div>

                        <!-- Team -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p
                                    class="text-sm font-medium text-surface-800 truncate"
                                >
                                    {{ team.name }}
                                </p>

                                <span
                                    :class="[
                                        'text-[10px] px-2 py-0.5 rounded-full',
                                        team.is_active
                                            ? 'bg-emerald-50 text-emerald-600'
                                            : 'bg-surface-100 text-surface-500',
                                    ]"
                                >
                                    {{
                                        team.is_active
                                            ? "Active"
                                            : "Inactive"
                                    }}
                                </span>
                            </div>

                            <p class="text-xs text-surface-400 mt-0.5">
                                {{ team.slug }}
                            </p>
                        </div>

                        <!-- Users -->
                        <div class="hidden sm:block text-right shrink-0">
                            <p class="text-sm font-medium text-surface-700">
                                {{ team.users_count ?? 0 }}
                            </p>

                            <p class="text-[10px] text-surface-400">Users</p>
                        </div>

                        <!-- WhatsApp -->
                        <div class="hidden md:block text-right min-w-[120px]">
                            <p
                                v-if="team.whatsapp_number"
                                class="text-xs font-mono text-surface-600"
                            >
                                {{ team.whatsapp_number.name }}
                            </p>

                            <p v-else class="text-xs text-surface-400">
                                Not configured
                            </p>

                            <p v-if="team.whatsapp_number" class="text-[10px] text-surface-400 mt-0.5">
                                {{ team.whatsapp_number.phone_number }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="py-12 text-center">
                    <div class="text-2xl mb-2">🏢</div>

                    <p class="text-sm text-surface-500">
                        No teams created yet.
                    </p>
                </div>
            </div>

            <!-- ─────────────────────────────────────────────
                 Platform Information
            ───────────────────────────────────────────── -->
            <div class="bg-green-500 rounded-2xl p-5 text-white">
                <div class="flex items-start gap-4">
                    <div
                        class="w-10 h-10 rounded-full bg-white/100 flex items-center justify-center shrink-0"
                    >
                        ℹ️
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold">
                            Official WhatsApp API
                        </h3>

                        <p
                            class="text-xs text-surface-400 mt-1 leading-relaxed"
                        >
                            WhatsApp communication is now managed through the
                            official API. A WhatsApp number can be assigned to
                            multiple teams while each team continues to
                            operate independently.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },

    teams: {
        type: Array,
        default: () => [],
    },
});

const { success, error, info } = useToast();
</script>
