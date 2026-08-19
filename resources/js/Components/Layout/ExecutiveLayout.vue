<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";

import { Head, Link, router, usePage } from "@inertiajs/vue3";

import NavItem from "@/Components/Layout/NavItem.vue";

import { Bell, UsersIcon, Home, LogOut, InboxIcon, X } from "lucide-vue-next";

import ToastStack from "@/Components/UI/ToastStack.vue";
import FlashToastBridge from "@/Components/UI/FlashToastBridge.vue";

import { useEcho } from "@/Composables/useEcho";

const props = defineProps({
    title: {
        type: String,
        default: "",
    },
});

const page = usePage();

/*
|--------------------------------------------------------------------------
| User
|--------------------------------------------------------------------------
*/

const user = computed(() => page.props.auth?.user ?? {});

/*
|--------------------------------------------------------------------------
| Team
|--------------------------------------------------------------------------
*/

const currentTeam = computed(() => {
    return page.props.workspace?.current_team ?? null;
});

/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

const sidebarOpen = ref(false);

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

const notificationOpen = ref(false);

const unreadCount = ref(0);

const unreadChatCount = ref(0);

const unreadChats = ref([]);

const unreadLoading = ref(false);

const unreadError = ref(null);

/*
|--------------------------------------------------------------------------
| Prevent duplicate realtime processing
|--------------------------------------------------------------------------
*/

const receivedMessageIds = new Set();

/*
|--------------------------------------------------------------------------
| Sidebar mounted
|--------------------------------------------------------------------------
*/

onMounted(() => {
    if (window.innerWidth >= 640) {
        sidebarOpen.value = true;
    }
});

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
    unreadLoading.value = true;

    unreadError.value = null;

    try {
        const response = await fetch(
            route("executive.messages.unread"),
            {
                method: "GET",

                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },

                credentials: "same-origin",
            },
        );

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
        console.error("Unable to load executive unread messages:", error);

        unreadError.value = "Unable to load notifications.";
    } finally {
        unreadLoading.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Message preview
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
| Notification time
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
| We do NOT mark the message read here.
|
| The actual Executive WhatsApp Inbox conversation should handle
| marking messages as read.
|
*/

const openUnreadChat = (chat) => {
    closeNotifications();

    const url = new URL(route("executive.messages.show", chat.customer_id));

    if (chat.whatsapp_number_id) {
        url.searchParams.set("whatsapp_number_id", String(chat.whatsapp_number_id));
    }

    router.visit(url.toString());
};

/*
|--------------------------------------------------------------------------
| Open full inbox
|--------------------------------------------------------------------------
*/

const openInbox = () => {
    closeNotifications();

    router.visit(route("executive.messages.index"));
};

/*
|--------------------------------------------------------------------------
| Realtime message handler
|--------------------------------------------------------------------------
*/

const handleRealtimeMessage = (payload) => {
    const message = payload?.message ?? payload;

    if (!message) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Only inbound messages create unread notifications
    |--------------------------------------------------------------------------
    */

    if (message.direction !== "inbound") {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Team safety check
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Executive ownership check
    |--------------------------------------------------------------------------
    |
    | This is only a frontend optimization.
    |
    | The backend endpoint remains the authoritative source.
    |
    */

    const customer = message.customer ?? null;

    if (!customer) {
        loadUnreadMessages();

        return;
    }

    const isAssignedToMe =
        Number(customer.assigned_to) === Number(user.value?.id);

    const isOldOwner = Number(customer.old_owner_id) === Number(user.value?.id);

    /*
    |--------------------------------------------------------------------------
    | Ignore messages belonging to another executive
    |--------------------------------------------------------------------------
    */

    if (!isAssignedToMe && !isOldOwner) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate protection
    |--------------------------------------------------------------------------
    */

    if (message.id) {
        if (receivedMessageIds.has(message.id)) {
            return;
        }

        receivedMessageIds.add(message.id);
    }

    /*
    |--------------------------------------------------------------------------
    | Reload authoritative server state
    |--------------------------------------------------------------------------
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
| Subscribe to team channel
|--------------------------------------------------------------------------
*/

const subscribeToTeamChannel = () => {
    if (!currentTeam.value?.id) {
        return;
    }

    const echo = useEcho().echo;

    const channelName = `whatsapp.team.${currentTeam.value.id}`;

    /*
    |--------------------------------------------------------------------------
    | Leave existing channel
    |--------------------------------------------------------------------------
    */

    if (teamChannel) {
        try {
            teamChannel.stopListening(".message.received");

            teamChannel.stopListening(".message.created");

            echo.leave(`private-${channelName}`);
        } catch (error) {
            console.warn("Unable to leave previous WhatsApp channel:", error);
        }

        teamChannel = null;
    }

    /*
    |--------------------------------------------------------------------------
    | Subscribe
    |--------------------------------------------------------------------------
    */

    teamChannel = echo.private(channelName);

    /*
    |--------------------------------------------------------------------------
    | Inbound message
    |--------------------------------------------------------------------------
    */

    teamChannel.listen(".message.received", handleRealtimeMessage);

    /*
    |--------------------------------------------------------------------------
    | Message created
    |--------------------------------------------------------------------------
    |
    | Your backend currently has multiple events.
    |
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
| Polling
|--------------------------------------------------------------------------
|
| This makes the notification count self-healing even if a websocket
| event is missed.
|
*/

let pollInterval = null;

const POLL_INTERVAL_MS = 10_000;

/*
|--------------------------------------------------------------------------
| Outside click
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
    |--------------------------------------------------------------------------
    | Initial unread state
    |--------------------------------------------------------------------------
    */

    await loadUnreadMessages();

    /*
    |--------------------------------------------------------------------------
    | Echo
    |--------------------------------------------------------------------------
    */

    subscribeToTeamChannel();

    /*
    |--------------------------------------------------------------------------
    | Poll every 10 seconds
    |--------------------------------------------------------------------------
    */

    pollInterval = setInterval(loadUnreadMessages, POLL_INTERVAL_MS);

    /*
    |--------------------------------------------------------------------------
    | Close notification when clicking outside
    |--------------------------------------------------------------------------
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

    if (pollInterval) {
        clearInterval(pollInterval);

        pollInterval = null;
    }

    document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
    <Head :title="props.title" />

    <div class="flex h-screen bg-surface-50 overflow-hidden">
        <!-- ============================================================= -->
        <!-- Sidebar -->
        <!-- ============================================================= -->

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
                        Executive
                    </span>
                </span>
            </div>

            <!-- ========================================================= -->
            <!-- Navigation -->
            <!-- ========================================================= -->

            <nav
                class="flex-1 py-4 space-y-1 px-2 overflow-y-auto scrollbar-thin"
            >
                <!-- Dashboard -->
                <NavItem
                    :href="route('dashboard')"
                    label="Dashboard"
                    :icon="Home"
                    :open="sidebarOpen"
                />

                <!-- Customers -->
                <NavItem
                    :href="route('executive.customers.index')"
                    label="Customers"
                    :icon="UsersIcon"
                    :open="sidebarOpen"
                />

                <!-- WhatsApp Inbox -->
                <NavItem
                    :href="route('executive.messages.index')"
                    label="WhatsApp Inbox"
                    :icon="InboxIcon"
                    :open="sidebarOpen"
                />
            </nav>

            <!-- ========================================================= -->
            <!-- Current Team / WhatsApp -->
            <!-- ========================================================= -->

            <div class="px-3 py-3 border-t border-surface-800">
                <div
                    v-if="sidebarOpen"
                    class="rounded-xl bg-surface-800 px-3 py-2"
                >
                    <p
                        class="text-[10px] uppercase tracking-widest text-surface-500"
                    >
                        Team
                    </p>

                    <p class="text-xs font-medium text-white truncate mt-1">
                        {{ currentTeam?.name || "Not assigned" }}
                    </p>

                    <p
                        class="text-[10px] uppercase tracking-widest text-surface-500 mt-3"
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

                <div v-else class="flex justify-center">
                    <div
                        class="w-10 h-10 rounded-lg bg-surface-800 border border-surface-700 flex items-center justify-center text-xs font-semibold"
                        :title="currentTeam?.name || 'No team'"
                    >
                        {{ currentTeam?.name?.charAt(0)?.toUpperCase() || "—" }}
                    </div>
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

            <!-- ========================================================= -->
            <!-- User -->
            <!-- ========================================================= -->

            <div
                class="p-3 border-t border-surface-800 flex items-center gap-2"
            >
                <img
                    :src="user.avatar_url"
                    class="w-8 h-8 rounded-full shrink-0 object-cover"
                    alt="User"
                />

                <div v-if="sidebarOpen" class="min-w-0 flex-1">
                    <p class="text-xs font-medium truncate">
                        {{ user.name || "Executive" }}
                    </p>

                    <p class="text-xs text-surface-400 truncate">Executive</p>
                </div>

                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="ml-auto text-surface-400 hover:text-white"
                    type="button"
                >
                    {{ sidebarOpen ? "‹" : "›" }}
                </button>
            </div>
        </aside>

        <!-- ============================================================= -->
        <!-- Mobile Overlay -->
        <!-- ============================================================= -->

        <div
            v-if="sidebarOpen"
            class="fixed inset-0 bg-black/40 sm:hidden z-30"
            @click="sidebarOpen = false"
        />

        <!-- ============================================================= -->
        <!-- Main -->
        <!-- ============================================================= -->

        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Top Bar -->
            <header
                class="h-14 bg-white border-b border-surface-100 flex items-center px-4 gap-4 shrink-0"
            >
                <!-- Mobile menu -->
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="sm:hidden text-surface-500"
                    type="button"
                >
                    ☰
                </button>

                <!-- Page title -->
                <div class="flex-1">
                    <slot name="header">
                        <h1 class="text-base font-semibold text-surface-900">
                            {{ props.title }}
                        </h1>
                    </slot>
                </div>

                <!-- Header actions -->
                <div class="flex items-center gap-2">
                    <slot name="actions" />

                    <!-- ========================================================= -->
                    <!-- Notification -->
                    <!-- ========================================================= -->

                    <div
                        class="relative"
                        data-notification-container
                    >
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
                                class="w-5 h-5"
                                :class="
                                    unreadCount > 0
                                        ? 'text-surface-900'
                                        : 'text-surface-500'
                                "
                            />

                            <!-- Badge -->
                            <span
                                v-if="unreadCount > 0"
                                class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center border-2 border-white"
                            >
                                {{
                                    unreadCount > 99
                                        ? "99+"
                                        : unreadCount
                                }}
                            </span>
                        </button>


                        <!-- ======================================================= -->
                        <!-- Notification Dropdown -->
                        <!-- ======================================================= -->

                        <div
                            v-if="notificationOpen"
                            class="absolute right-0 top-11 w-[360px] max-w-[calc(100vw-2rem)] bg-white border border-surface-200 rounded-xl shadow-xl z-50 overflow-hidden"
                        >

                            <!-- Header -->
                            <div
                                class="px-5 py-4 border-b border-surface-200 flex items-center justify-between"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-surface-900"
                                    >
                                        Notifications
                                    </h3>

                                    <p
                                        class="text-xs text-surface-500 mt-0.5"
                                    >
                                        {{
                                            unreadChatCount
                                        }}
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
                                    class="text-surface-400 hover:text-surface-700"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>


                            <!-- Loading -->
                            <div
                                v-if="unreadLoading"
                                class="px-5 py-8 text-center"
                            >
                                <p
                                    class="text-xs text-surface-500"
                                >
                                    Loading notifications...
                                </p>
                            </div>


                            <!-- Error -->
                            <div
                                v-else-if="unreadError"
                                class="px-5 py-8 text-center"
                            >
                                <p
                                    class="text-sm text-red-600"
                                >
                                    {{ unreadError }}
                                </p>

                                <button
                                    type="button"
                                    @click="loadUnreadMessages"
                                    class="mt-2 text-xs text-surface-600 hover:text-surface-900"
                                >
                                    Try again
                                </button>
                            </div>


                            <!-- Empty -->
                            <div
                                v-else-if="!unreadChats.length"
                                class="px-5 py-10 text-center"
                            >
                                <Bell
                                    class="w-7 h-7 text-surface-300 mx-auto"
                                />

                                <p
                                    class="text-sm font-medium text-surface-700 mt-2"
                                >
                                    No unread messages
                                </p>

                                <p
                                    class="text-xs text-surface-500 mt-1"
                                >
                                    You're all caught up.
                                </p>
                            </div>


                            <!-- Notifications -->
                            <div
                                v-else
                                class="max-h-[380px] overflow-y-auto"
                            >
                                <button
                                    v-for="chat in unreadChats"
                                    :key="chat.message_id"
                                    type="button"
                                    @click="openUnreadChat(chat)"
                                    class="w-full text-left px-5 py-4 border-b border-surface-100 hover:bg-surface-50 transition"
                                >
                                    <div class="flex items-start gap-3">

                                        <!-- Avatar -->
                                        <div
                                            class="w-9 h-9 rounded-full bg-red-50 text-red-600 flex items-center justify-center text-xs font-semibold shrink-0"
                                        >
                                            {{
                                                chat.customer_name
                                                    ?.charAt(0)
                                                    ?.toUpperCase() || "C"
                                            }}
                                        </div>


                                        <!-- Content -->
                                        <div
                                            class="min-w-0 flex-1"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-2"
                                            >
                                                <p
                                                    class="text-sm font-medium text-surface-900 truncate"
                                                >
                                                    {{
                                                        chat.customer_name ||
                                                        "Customer"
                                                    }}
                                                </p>

                                                <span
                                                    class="text-[10px] text-surface-400 whitespace-nowrap"
                                                >
                                                    {{
                                                        formatNotificationTime(
                                                            chat
                                                        )
                                                    }}
                                                </span>
                                            </div>


                                            <div
                                                class="flex items-center gap-2 mt-1"
                                            >
                                                <span
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-red-50 text-red-600 text-[9px] font-medium"
                                                >
                                                    Unread
                                                </span>
                                            </div>


                                            <p
                                                class="text-xs text-surface-500 mt-1 line-clamp-2"
                                            >
                                                {{
                                                    messagePreview(chat)
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </button>
                            </div>


                            <!-- Footer -->
                            <button
                                type="button"
                                @click="openInbox"
                                class="w-full px-5 py-3 border-t border-surface-200 text-xs font-medium text-surface-600 hover:bg-surface-50 transition"
                            >
                                View WhatsApp Inbox →
                            </button>
                        </div>
                    </div>

                    <!-- Sign out -->
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
        <FlashToastBridge />
    </div>
</template>
