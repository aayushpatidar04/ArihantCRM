<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

import { Head, Link, router, usePage } from "@inertiajs/vue3";

import NavItem from "@/Components/Layout/NavItem.vue";

import {
    Bell,
    Users,
    Home,
    LogOut,
    UsersIcon,
    InboxIcon,
    MessageCircle,
    X,
} from "lucide-vue-next";

import ToastStack from "@/Components/UI/ToastStack.vue";

import { useEcho } from "@/Composables/useEcho";

const props = defineProps({
    title: {
        type: String,
        default: "",
    },
});

const page = usePage();

const sidebarOpen = ref(false);

const user = computed(() => page.props.auth?.user ?? {});

const accessibleTeams = computed(() => page.props.workspace?.teams ?? []);

const currentTeam = computed(() => page.props.workspace?.current_team ?? null);

const selectedTeam = ref(null);

/*
|--------------------------------------------------------------------------
| Workspace
|--------------------------------------------------------------------------
*/

watch(
    currentTeam,
    (team) => {
        selectedTeam.value = team?.id ?? null;
    },
    {
        immediate: true,
    },
);

const isTeamAdmin = computed(() => user.value.roles?.includes("team_admin"));

/*
|--------------------------------------------------------------------------
| Workspace switching
|--------------------------------------------------------------------------
*/

const switchWorkspace = () => {
    if (!selectedTeam.value) {
        return;
    }

    if (Number(selectedTeam.value) === Number(currentTeam.value?.id)) {
        return;
    }

    router.post(
        route("team-admin.workspace.switch"),
        {
            team_id: selectedTeam.value,
        },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

onMounted(() => {
    if (window.innerWidth >= 640) {
        sidebarOpen.value = true;
    }
});

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

const notificationOpen = ref(false);

const unreadCount = ref(0);

const unreadChatCount = ref(0);

const unreadChats = ref([]);

/*
|--------------------------------------------------------------------------
| Loading state
|--------------------------------------------------------------------------
*/

const unreadLoading = ref(false);

/*
|--------------------------------------------------------------------------
| Error state
|--------------------------------------------------------------------------
*/

const unreadError = ref(null);

/*
|--------------------------------------------------------------------------
| Prevent duplicate real-time messages
|--------------------------------------------------------------------------
|
| If both MessageCreated and NewInboundMessage are triggered
| for the same message, don't increment the notification twice.
|
*/

const receivedMessageIds = new Set();

/*
|--------------------------------------------------------------------------
| Notification panel
|--------------------------------------------------------------------------
*/

const toggleNotifications = () => {
    notificationOpen.value = !notificationOpen.value;

    if (notificationOpen.value) {
        loadUnreadMessages();
    }
};

const closeNotifications = () => {
    notificationOpen.value = false;
};

/*
|--------------------------------------------------------------------------
| Load unread messages
|--------------------------------------------------------------------------
*/

const loadUnreadMessages = async () => {
    if (!currentTeam.value?.id) {
        unreadCount.value = 0;
        unreadChatCount.value = 0;
        unreadChats.value = [];

        return;
    }

    unreadLoading.value = true;
    unreadError.value = null;

    try {
        const response = await fetch(route("team-admin.dashboard.unread-messages"), {
            method: "GET",

            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },

            credentials: "same-origin",
        });

        if (!response.ok) {
            throw new Error("Unable to load unread messages.");
        }

        const data = await response.json();

        unreadCount.value = Number(data.unread_count ?? 0);

        unreadChatCount.value = Number(data.unread_chat_count ?? 0);

        unreadChats.value = Array.isArray(data.unread_chats)
            ? data.unread_chats
            : [];
    } catch (error) {
        console.error("Unable to load unread messages:", error);

        unreadError.value = "Unable to load notifications.";
    } finally {
        unreadLoading.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Format message preview
|--------------------------------------------------------------------------
*/

const messagePreview = (chat) => {
    if (!chat) {
        return "";
    }

    if (chat.body) {
        return chat.body;
    }

    switch (chat.type) {
        case "image":
            return "📷 Image";

        case "video":
            return "🎥 Video";

        case "audio":
            return "🎵 Audio";

        case "document":
            return "📎 Document";

        case "sticker":
            return "Sticker";

        default:
            return "New message";
    }
};

/*
|--------------------------------------------------------------------------
| Format time
|--------------------------------------------------------------------------
*/

const formatNotificationTime = (chat) => {
    if (chat?.time_ago) {
        return chat.time_ago;
    }

    if (!chat?.created_at) {
        return "";
    }

    const date = new Date(chat.created_at);

    if (Number.isNaN(date.getTime())) {
        return "";
    }

    return date.toLocaleString();
};

/*
|--------------------------------------------------------------------------
| Open unread conversation
|--------------------------------------------------------------------------
|
| We don't mark the message read here because that should be handled
| by the actual WhatsApp Inbox conversation/read logic.
|
| We simply take the Team Admin to the Inbox.
|
*/

const openUnreadChat = (chat) => {
    closeNotifications();

    /*
     * Pass the conversation information to the inbox.
     *
     * The Inbox can use these query parameters to open the
     * correct customer/WhatsApp conversation.
     */

    router.visit(
        route("team-admin.messages.show", chat.customer_id),
    );
};

/*
|--------------------------------------------------------------------------
| Open full inbox
|--------------------------------------------------------------------------
*/

const openInbox = () => {
    closeNotifications();

    router.visit(route("team-admin.messages.index"));
};

/*
|--------------------------------------------------------------------------
| Handle realtime inbound message
|--------------------------------------------------------------------------
*/

const handleRealtimeMessage = (payload) => {
    const message = payload?.message ?? payload;

    if (!message) {
        return;
    }

    /*
     * Only inbound messages should affect unread notifications.
     */

    if (message.direction !== "inbound") {
        return;
    }

    /*
     * Ensure this belongs to the current workspace.
     */

    if (Number(message.team_id) !== Number(currentTeam.value?.id)) {
        return;
    }

    /*
     * If this message already exists in the current
     * unread list, don't increment again.
     */

    if (message.id) {
        if (receivedMessageIds.has(message.id)) {
            return;
        }

        receivedMessageIds.add(message.id);
    }

    /*
     * Safest approach:
     *
     * Instead of manually trying to calculate all unread state,
     * reload the authoritative server state.
     *
     * This handles:
     *
     * - multiple messages
     * - messages arriving quickly
     * - multiple WhatsApp numbers
     * - read_at changes
     * - duplicate events
     */

    loadUnreadMessages();
};

/*
|--------------------------------------------------------------------------
| Echo
|--------------------------------------------------------------------------
*/

let teamChannel = null;

/*
|--------------------------------------------------------------------------
| Subscribe to current Team channel
|--------------------------------------------------------------------------
*/

const subscribeToTeamChannel = () => {
    if (!currentTeam.value?.id) {
        return;
    }

    const echo = useEcho().echo;

    const channelName = `whatsapp.team.${currentTeam.value.id}`;

    /*
     * Leave old channel first.
     */

    if (teamChannel) {
        try {
            echo.leave(`private-${channelName}`);
        } catch (error) {
            console.warn("Unable to leave previous WhatsApp channel:", error);
        }

        teamChannel = null;
    }

    /*
     * Subscribe to private team channel.
     */

    teamChannel = echo.private(channelName);

    /*
     * Inbound message.
     */

    teamChannel.listen(".message.received", handleRealtimeMessage);

    /*
     * MessageCreated is also listened to because your backend
     * currently has both event types.
     *
     * handleRealtimeMessage() only reacts to inbound messages
     * and deduplicates by message ID.
     */

    teamChannel.listen(".message.created", handleRealtimeMessage);
};

/*
|--------------------------------------------------------------------------
| Unsubscribe
|--------------------------------------------------------------------------
*/

const unsubscribeFromTeamChannel = () => {
    if (!teamChannel || !currentTeam.value?.id) {
        return;
    }

    const echo = useEcho().echo;

    const channelName = `whatsapp.team.${currentTeam.value.id}`;

    teamChannel.stopListening(".message.received");

    teamChannel.stopListening(".message.created");

    echo.leave(`private-${channelName}`);

    teamChannel = null;
};

/*
|--------------------------------------------------------------------------
| Current team changed
|--------------------------------------------------------------------------
*/

watch(
    () => currentTeam.value?.id,
    async (teamId, oldTeamId) => {
        if (teamId && Number(teamId) !== Number(oldTeamId)) {
            /*
             * Reset realtime deduplication because
             * we're now looking at another workspace.
             */

            receivedMessageIds.clear();

            /*
             * Load unread state for new team.
             */

            await loadUnreadMessages();

            /*
             * Subscribe to new team's private channel.
             */

            subscribeToTeamChannel();
        }
    },
);

/*
|--------------------------------------------------------------------------
| Close notification panel when clicking outside
|--------------------------------------------------------------------------
*/

const handleDocumentClick = (event) => {
    const notificationElement = document.querySelector(
        "[data-notification-container]",
    );

    if (notificationElement && !notificationElement.contains(event.target)) {
        notificationOpen.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Mount
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    /*
     * Load unread notification state immediately.
     */

    await loadUnreadMessages();

    /*
     * Start realtime listener.
     */

    subscribeToTeamChannel();

    /*
     * Outside click handler.
     */

    document.addEventListener("click", handleDocumentClick);
});

/*
|--------------------------------------------------------------------------
| Unmount
|--------------------------------------------------------------------------
*/

onUnmounted(() => {
    unsubscribeFromTeamChannel();

    document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
    <Head :title="props.title" />

    <div class="flex h-screen bg-surface-50 overflow-hidden">
        <!-- ========================================================= -->
        <!-- Sidebar -->
        <!-- ========================================================= -->

        <aside
            :class="[
                'flex flex-col bg-surface-900 text-white transition-all duration-300 shrink-0',
                sidebarOpen ? 'w-64' : 'w-21',
                'sm:relative sm:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                'fixed sm:static h-full z-40',
            ]"
        >
            <!-- Logo -->

            <div
                class="flex items-center gap-3 px-4 py-5 border-b border-surface-800"
            >
                <div
                    class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                >
                    <img
                        src="/assets/images/InTouchConnect.webp"
                        alt="InTouch Connect"
                        class="w-12 h-12 rounded-full object-cover border-2 border-white"
                    />
                </div>

                <span
                    v-if="sidebarOpen"
                    class="font-semibold text-md tracking-wide whitespace-nowrap"
                >
                    InTouch Connect<br />

                    <span
                        class="text-xs text-amber-400 font-medium whitespace-nowrap"
                    >
                        Team Admin
                    </span>
                </span>
            </div>

            <!-- Workspace -->

            <div class="px-3 py-3 border-b border-surface-800">
                <div v-if="sidebarOpen">
                    <p
                        class="text-[11px] uppercase tracking-widest text-surface-500 font-medium mb-2"
                    >
                        Workspace
                    </p>

                    <select
                        v-model="selectedTeam"
                        @change="switchWorkspace"
                        class="w-full bg-surface-800 border border-surface-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option
                            v-for="team in accessibleTeams"
                            :key="team.id"
                            :value="team.id"
                        >
                            {{ team.name }}
                        </option>
                    </select>
                </div>

                <!-- Collapsed -->

                <div v-else class="flex justify-center">
                    <div
                        class="w-10 h-10 rounded-lg bg-surface-800 border border-surface-700 flex items-center justify-center text-xs font-semibold"
                        :title="currentTeam?.name || 'No workspace'"
                    >
                        {{ currentTeam?.name?.charAt(0)?.toUpperCase() || "—" }}
                    </div>
                </div>
            </div>

            <!-- Navigation -->

            <nav
                class="flex-1 py-4 space-y-1 px-2 overflow-y-auto scrollbar-thin"
            >
                <NavItem
                    :href="route('dashboard')"
                    label="Dashboard"
                    :icon="Home"
                    :open="sidebarOpen"
                />

                <NavItem
                    :href="route('team-admin.customers.index')"
                    label="Customers"
                    :icon="UsersIcon"
                    :open="sidebarOpen"
                />

                <NavItem
                    :href="route('team-admin.messages.index')"
                    label="WhatsApp Inbox"
                    :icon="InboxIcon"
                    :open="sidebarOpen"
                />

                <NavItem
                    :href="route('team-admin.users.index')"
                    label="Users"
                    :icon="Users"
                    :open="sidebarOpen"
                />
            </nav>

            <!-- Current workspace / WhatsApp -->

            <div class="px-3 py-3 border-t border-surface-800">
                <div
                    v-if="sidebarOpen"
                    class="rounded-xl bg-surface-800 px-3 py-2"
                >
                    <p
                        class="text-[10px] uppercase tracking-widest text-surface-500"
                    >
                        WhatsApp
                    </p>

                    <p class="text-xs font-medium text-white truncate mt-1">
                        {{
                            currentTeam?.whatsapp_number
                                ?.display_phone_number || "Not assigned"
                        }}
                    </p>
                </div>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-surface-400 hover:text-white hover:bg-surface-800 transition-colors w-full mt-1"
                >
                    <LogOut />

                    <span v-if="sidebarOpen"> Sign out </span>
                </Link>
            </div>

            <!-- User -->

            <div
                class="p-3 border-t border-surface-800 flex items-center gap-2"
            >
                <img
                    :src="user.avatar_url"
                    class="w-8 h-8 rounded-full shrink-0 object-cover"
                />

                <div v-if="sidebarOpen" class="min-w-0 flex-1">
                    <p class="text-xs font-medium truncate">
                        {{ user.name }}
                    </p>

                    <p class="text-xs text-surface-400 truncate">Team Admin</p>
                </div>

                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="ml-auto text-surface-400 hover:text-white"
                >
                    {{ sidebarOpen ? "‹" : "›" }}
                </button>
            </div>
        </aside>

        <!-- Mobile overlay -->

        <div
            v-if="sidebarOpen"
            class="fixed inset-0 bg-black/40 sm:hidden z-30"
            @click="sidebarOpen = false"
        />

        <!-- ========================================================= -->
        <!-- Main -->
        <!-- ========================================================= -->

        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Top bar -->

            <header
                class="h-14 bg-white border-b border-surface-100 flex items-center px-4 gap-4 shrink-0"
            >
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="sm:hidden text-surface-500"
                >
                    ☰
                </button>

                <div class="flex-1">
                    <slot name="header">
                        <h1 class="text-base font-semibold text-surface-900">
                            {{ props.title }}
                        </h1>
                    </slot>
                </div>

                <div class="flex items-center gap-2">
                    <slot name="actions" />

                    <!-- ================================================= -->
                    <!-- Notification Bell -->
                    <!-- ================================================= -->

                    <div class="relative" data-notification-container>
                        <button
                            type="button"
                            @click.stop="toggleNotifications"
                            class="relative w-9 h-9 flex items-center justify-center rounded-lg text-surface-500 hover:text-surface-900 hover:bg-surface-100 transition"
                            :aria-label="
                                unreadCount > 0
                                    ? `${unreadCount} unread messages`
                                    : 'Notifications'
                            "
                        >
                            <Bell
                                :size="19"
                                :class="[
                                    unreadCount > 0
                                        ? 'text-surface-900'
                                        : 'text-surface-500',
                                ]"
                            />

                            <!-- Badge -->

                            <span
                                v-if="unreadCount > 0"
                                class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center border-2 border-white"
                            >
                                {{ unreadCount > 99 ? "99+" : unreadCount }}
                            </span>
                        </button>

                        <!-- ================================================= -->
                        <!-- Notification Dropdown -->
                        <!-- ================================================= -->

                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 scale-95 -translate-y-2"
                            enter-to-class="opacity-100 scale-100 translate-y-0"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100 scale-100 translate-y-0"
                            leave-to-class="opacity-0 scale-95 -translate-y-2"
                        >
                            <div
                                v-if="notificationOpen"
                                @click.stop
                                class="absolute right-0 top-11 w-[380px] max-w-[calc(100vw-2rem)] bg-white border border-surface-200 rounded-xl shadow-xl z-50 overflow-hidden"
                            >
                                <!-- Header -->

                                <div
                                    class="px-4 py-3 border-b border-surface-100 flex items-center justify-between"
                                >
                                    <div>
                                        <h3
                                            class="text-sm font-semibold text-surface-900"
                                        >
                                            Notifications
                                        </h3>

                                        <p
                                            class="text-[11px] text-surface-500 mt-0.5"
                                        >
                                            {{ unreadCount }}
                                            unread
                                            {{
                                                unreadChatCount === 1
                                                    ? "conversation"
                                                    : "conversations"
                                            }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        @click="closeNotifications"
                                        class="w-7 h-7 rounded-lg flex items-center justify-center text-surface-400 hover:text-surface-700 hover:bg-surface-100"
                                    >
                                        <X :size="16" />
                                    </button>
                                </div>

                                <!-- Loading -->

                                <div
                                    v-if="unreadLoading"
                                    class="px-4 py-10 text-center"
                                >
                                    <div
                                        class="w-5 h-5 border-2 border-surface-300 border-t-surface-800 rounded-full animate-spin mx-auto"
                                    />

                                    <p class="text-xs text-surface-500 mt-3">
                                        Loading unread messages...
                                    </p>
                                </div>

                                <!-- Error -->

                                <div
                                    v-else-if="unreadError"
                                    class="px-4 py-8 text-center"
                                >
                                    <p class="text-sm text-red-600">
                                        {{ unreadError }}
                                    </p>

                                    <button
                                        type="button"
                                        @click="loadUnreadMessages"
                                        class="text-xs text-surface-700 font-medium mt-2 hover:underline"
                                    >
                                        Try again
                                    </button>
                                </div>

                                <!-- No unread messages -->

                                <div
                                    v-else-if="!unreadChats.length"
                                    class="px-4 py-10 text-center"
                                >
                                    <div
                                        class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto"
                                    >
                                        ✓
                                    </div>

                                    <p
                                        class="text-sm font-medium text-surface-900 mt-3"
                                    >
                                        All caught up
                                    </p>

                                    <p class="text-xs text-surface-500 mt-1">
                                        You have no unread messages.
                                    </p>
                                </div>

                                <!-- Unread conversations -->

                                <div
                                    v-else
                                    class="max-h-[420px] overflow-y-auto divide-y divide-surface-100"
                                >
                                    <button
                                        v-for="chat in unreadChats"
                                        :key="chat.conversation_key"
                                        type="button"
                                        @click="openUnreadChat(chat)"
                                        class="w-full text-left px-4 py-3 hover:bg-surface-50 transition flex gap-3"
                                    >
                                        <!-- Avatar -->

                                        <div
                                            class="w-9 h-9 rounded-full bg-surface-900 text-white flex items-center justify-center shrink-0 text-xs font-semibold"
                                        >
                                            {{
                                                chat.customer_name
                                                    ?.charAt(0)
                                                    ?.toUpperCase() || "?"
                                            }}
                                        </div>

                                        <!-- Content -->

                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="flex items-start justify-between gap-2"
                                            >
                                                <p
                                                    class="text-sm font-semibold text-surface-900 truncate"
                                                >
                                                    {{ chat.customer_name }}
                                                </p>

                                                <span
                                                    class="text-[10px] text-surface-400 whitespace-nowrap"
                                                >
                                                    {{
                                                        formatNotificationTime(
                                                            chat,
                                                        )
                                                    }}
                                                </span>
                                            </div>

                                            <p
                                                v-if="chat.whatsapp_number"
                                                class="text-[10px] text-surface-400 mt-0.5"
                                            >
                                                {{ chat.whatsapp_number }}
                                            </p>

                                            <div
                                                class="flex items-center gap-2 mt-1"
                                            >
                                                <p
                                                    class="text-xs text-surface-500 truncate flex-1"
                                                >
                                                    {{ messagePreview(chat) }}
                                                </p>

                                                <!-- Unread count -->

                                                <span
                                                    class="min-w-[20px] h-5 px-1.5 rounded-full bg-red-50 text-red-600 text-[10px] font-bold flex items-center justify-center shrink-0"
                                                >
                                                    {{ chat.unread_count }}
                                                </span>
                                            </div>
                                        </div>
                                    </button>
                                </div>

                                <!-- Footer -->

                                <div
                                    class="border-t border-surface-100 px-4 py-2.5"
                                >
                                    <button
                                        type="button"
                                        @click="openInbox"
                                        class="w-full text-center text-xs font-medium text-surface-700 hover:text-surface-900"
                                    >
                                        View WhatsApp Inbox
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="text-xs text-surface-400 hover:text-surface-700 px-2 py-1 rounded-lg hover:bg-surface-100"
                    >
                        Sign out
                    </Link>
                </div>
            </header>

            <!-- Content -->

            <main class="flex-1 overflow-y-auto scrollbar-thin p-4 sm:p-6">
                <slot />
            </main>
        </div>

        <ToastStack />
    </div>
</template>