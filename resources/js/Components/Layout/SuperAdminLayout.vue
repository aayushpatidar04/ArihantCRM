<template>
    <div class="min-h-screen bg-surface-50 flex">
        <!-- Sidebar -->
        <aside
            :class="[
                'bg-surface-900 text-white flex flex-col shrink-0 transition-all duration-200',
                sidebarOpen ? 'w-64' : 'w-16',
            ]"
        >
            <!-- Logo -->
            <div
                class="h-20 flex items-center px-4 border-b border-surface-800 shrink-0"
            >
                <Link
                    :href="route('dashboard')"
                    class="flex items-center gap-8 min-w-0 ml-3"
                >
                    <div
                        class="w-8 h-8 flex items-center justify-center shrink-0"
                    >
                        <img src="/assets/images/InTouchConnect.webp" alt="InTouch Connect" class="w-16 h-16 rounded-full object-cover absolute border-2 border-white" />
                    </div>

                    <span
                        v-if="sidebarOpen"
                        class="font-semibold text-sm whitespace-nowrap"
                    >
                        InTouch Connect<br>
                        <span class="text-xs text-amber-400 font-medium whitespace-nowrap">
                            Super Admin
                        </span>
                    </span>
                </Link>
            </div>

            <!-- Navigation -->
            <nav
                class="flex-1 py-4 space-y-1 px-2 overflow-y-auto scrollbar-thin"
            >
                <NavItem
                    :href="route('dashboard')"
                    :icon="HomeIcon"
                    label="Overview"
                    :open="sidebarOpen"
                />

                <div class="pt-3 pb-1 px-2">
                    <span
                        v-if="sidebarOpen"
                        class="text-[10px] uppercase tracking-widest text-surface-500 font-semibold"
                    >
                        Platform
                    </span>

                    <div v-else class="border-t border-surface-700 my-2" />
                </div>

                <NavItem
                    :href="route('superadmin.teams.index')"
                    :icon="Users"
                    label="Teams"
                    :open="sidebarOpen"
                />
                
                <NavItem
                    :href="route('superadmin.meta-whatsapp-settings.index')"
                    :icon="SettingsIcon"
                    label="Meta Apps"
                    :open="sidebarOpen"
                />

                <NavItem
                    :href="route('superadmin.whatsapp-numbers.index')"
                    :icon="PhoneIcon"
                    label="WhatsApp Numbers"
                    :open="sidebarOpen"
                />

                <NavItem
                    :href="route('superadmin.whatsapp-templates.index')"
                    :icon="FileBracesIcon"
                    label="Templates"
                    :open="sidebarOpen"
                />
            </nav>

            <!-- Super Admin Badge -->
            <div class="px-3 py-3 border-t border-surface-800">
                <div
                    class="flex items-center gap-2 rounded-xl px-3 py-2.5 bg-amber-500/10 text-amber-400"
                >
                    <svg
                        class="w-4 h-4 shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955
                           11.955 0 0112 2.944a11.955 11.955
                           0 01-8.618 3.04A12.02 12.02
                           0 003 9c0 5.591 3.824 10.29
                           9 11.622 5.176-1.332 9-6.03
                           9-11.622 0-1.042-.133-2.052-.382-3.016z"
                        />
                    </svg>

                    <span
                        v-if="sidebarOpen"
                        class="text-xs font-medium whitespace-nowrap"
                    >
                        Super Admin
                    </span>
                </div>
            </div>

            <!-- User + Collapse -->
            <div
                class="p-3 border-t border-surface-800 flex items-center gap-2"
            >
                <img
                    :src="$page.props.auth.user.avatar_url"
                    class="w-8 h-8 rounded-full shrink-0 object-cover"
                />

                <div v-if="sidebarOpen" class="min-w-0 flex-1">
                    <p class="text-xs font-medium truncate">
                        {{ $page.props.auth.user.name }}
                    </p>

                    <p class="text-[11px] text-surface-400 truncate">
                        {{ $page.props.auth.user.email }}
                    </p>
                </div>

                <button
                    type="button"
                    @click="sidebarOpen = !sidebarOpen"
                    class="ml-auto text-surface-400 hover:text-white transition-colors p-1 rounded-lg hover:bg-surface-800"
                >
                    <ChevronLeftIcon v-if="sidebarOpen" class="w-4 h-4" />

                    <ChevronRightIcon v-else class="w-4 h-4" />
                </button>
            </div>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Top Bar -->
            <header
                class="h-14 bg-white border-b border-surface-100 flex items-center px-6 gap-4 shrink-0"
            >
                <div class="flex-1 min-w-0">
                    <slot name="header">
                        <h1
                            class="text-base font-semibold text-surface-900 truncate"
                        >
                            {{ title }}
                        </h1>
                    </slot>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <slot name="actions" />

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="text-xs text-surface-400 hover:text-surface-700 transition-colors px-2.5 py-1.5 rounded-lg hover:bg-surface-100"
                    >
                        Sign out
                    </Link>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto scrollbar-thin">
                <slot />
            </main>
        </div>

        <!-- Toast -->
        <ToastStack />
        <FlashToastBridge />
    </div>
</template>

<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import NavItem from "@/Components/Layout/NavItem.vue";
import ToastStack from "@/Components/UI/ToastStack.vue";
import FlashToastBridge from "@/Components/UI/FlashToastBridge.vue";
import HomeIcon from "@/Components/Icons/HomeIcon.vue";
import ChevronLeftIcon from "@/Components/Icons/ChevronLeftIcon.vue";
import ChevronRightIcon from "@/Components/Icons/ChevronRightIcon.vue";
import { Users, FileBracesIcon, SettingsIcon, PhoneIcon } from "lucide-vue-next";

defineProps({ title: String });

const sidebarOpen = ref(true);
</script>
