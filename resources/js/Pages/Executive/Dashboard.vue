<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

import {
    Users,
    UserCheck,
    MessageCircle,
    MessagesSquare,
    ArrowRight,
    ArrowDownLeft,
    ArrowUpRight,
    CheckCircle2,
    XCircle,
} from "lucide-vue-next";

import ExecutiveLayout from "@/Components/Layout/ExecutiveLayout.vue";

import { usePrivateChannel } from "@/Composables/useEcho";

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },

    stats: {
        type: Object,
        required: true,
    },

    recentMessages: {
        type: Array,
        default: () => [],
    },

    messageChart: {
        type: Array,
        default: () => [],
    },
});

const formatNumber = (value) => {
    return new Intl.NumberFormat("en-IN").format(Number(value ?? 0));
};

/*
|--------------------------------------------------------------------------
| WhatsApp
|--------------------------------------------------------------------------
*/

const whatsappNumber = computed(() => props.team?.whatsapp_number ?? null);

const whatsappActive = computed(() => whatsappNumber.value?.is_active === true);

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

const statCards = computed(() => [
    {
        label: "My Customers",
        value: props.stats.total_customers ?? 0,
        icon: Users,
        description: "Customers assigned to you or previously owned by you.",
    },

    {
        label: "Active Customers",
        value: props.stats.active_customers ?? 0,
        icon: UserCheck,
        description: "Currently active customers in your portfolio.",
    },

    {
        label: "Unread Messages",
        value: unreadCount ?? 0,
        icon: MessageCircle,
        description: "Inbound messages waiting for your response.",
    },

    {
        label: "Messages Today",
        value: props.stats.messages_today ?? 0,
        icon: MessagesSquare,
        description: "Messages exchanged today.",
    },
]);

/*
|--------------------------------------------------------------------------
| Chart helpers
|--------------------------------------------------------------------------
*/

const chartData = computed(() => {
    const data = props.messageChart ?? [];

    return data.map((item) => ({
        ...item,

        total: Number(item.total ?? 0),

        inbound: Number(item.inbound ?? 0),

        outbound: Number(item.outbound ?? 0),
    }));
});

const chartMaximum = computed(() => {
    const maximum = Math.max(...chartData.value.map((item) => item.total), 1);

    return maximum;
});

const maxChartValue = computed(() => {
    if (!chartData.value.length) {
        return 1;
    }

    return Math.max(
        ...chartData.value.flatMap((item) => [
            Number(item.inbound) || 0,
            Number(item.outbound) || 0,
        ]),
        1,
    );
});

const chartBarHeight = (value) => {
    const number = Number(value) || 0;

    if (number === 0) {
        return 0;
    }

    return Math.max((number / maxChartValue.value) * 100, 4);
};

const formatChartDate = (date) => {
    if (!date) {
        return "";
    }

    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        weekday: "short",
    });
};

const formatDate = (value) => {
    if (!value) {
        return "—";
    }

    return new Date(value).toLocaleString();
};

/*
|--------------------------------------------------------------------------
| Message Preview
|--------------------------------------------------------------------------
*/

const messagePreview = (message) => {
    if (message.body) {
        return message.body;
    }

    switch (message.type) {
        case "image":
            return "Image";

        case "video":
            return "Video";

        case "audio":
            return "Audio";

        case "document":
            return "Document";

        case "template":
            return "Template message";

        default:
            return "Message";
    }
};

/*
|--------------------------------------------------------------------------
| Unread Messages
|--------------------------------------------------------------------------
*/

const unreadChats = ref([]);

const unreadChatCount = ref(
    Number(props.stats?.unread_messages ?? 0) > 0 ? 0 : 0,
);

const unreadCount = ref(Number(props.stats?.unread_messages ?? 0));

const loadingUnread = ref(false);

const lastUnreadRefresh = ref(null);

const fetchUnreadMessages = async () => {
    loadingUnread.value = true;

    try {
        const response = await fetch(route("executive.messages.unread"), {
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

        unreadChats.value = data.unread_chats ?? [];

        lastUnreadRefresh.value = new Date().toLocaleTimeString("en-IN", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        });
    } catch (error) {
        console.error("Failed to fetch executive unread messages:", error);
    } finally {
        loadingUnread.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Open Conversation
|--------------------------------------------------------------------------
*/

const openUnreadChat = (chat) => {
    if (!chat?.customer_id) {
        return;
    }

    router.visit(route("executive.messages.show", chat.customer_id));
};

/*
|--------------------------------------------------------------------------
| Polling
|--------------------------------------------------------------------------
*/

let unreadPollInterval = null;

const UNREAD_POLL_INTERVAL_MS = 10_000;

/*
|--------------------------------------------------------------------------
| Echo
|--------------------------------------------------------------------------
|
| We listen to the same team private channel that your existing
| MessageCreated / NewInboundMessage events broadcast on.
|
| New inbound message:
| whatsapp.team.{teamId}
|
*/

const subscribeToMessageBroadcast = () => {
    const teamId = props.team?.id;

    if (!teamId) {
        return;
    }

    usePrivateChannel(`whatsapp.team.${teamId}`, {
        "message.received": () => {
            /*
             * Don't wait for the next 10-second polling cycle.
             *
             * Immediately refresh unread messages.
             */
            fetchUnreadMessages();
        },

        "message.created": (event) => {
            /*
             * message.created can also happen for outgoing
             * messages. The endpoint itself decides what
             * is actually unread, so it is safe to refresh.
             */
            fetchUnreadMessages();
        },
    });
};

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(() => {
    fetchUnreadMessages();

    unreadPollInterval = setInterval(
        fetchUnreadMessages,
        UNREAD_POLL_INTERVAL_MS,
    );
});

onUnmounted(() => {
    if (unreadPollInterval) {
        clearInterval(unreadPollInterval);

        unreadPollInterval = null;
    }
});

usePrivateChannel(`whatsapp.team.${props.team?.id}`, {
    "message.received": () => {
        fetchUnreadMessages();
    },

    "message.created": () => {
        fetchUnreadMessages();
    },
});
</script>

<template>
    <Head title="Executive Dashboard" />

    <ExecutiveLayout title="Dashboard">
        <div class="space-y-6">
            <!-- ========================================================= -->
            <!-- Header -->
            <!-- ========================================================= -->

            <div
                class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4"
            >
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        Executive Dashboard
                    </h1>

                    <p class="mt-1 text-sm text-surface-500">
                        Overview of your customers, conversations and recent
                        activity.
                    </p>
                </div>

                <div
                    class="inline-flex items-center gap-2 self-start lg:self-auto px-3 py-2 rounded-lg border border-surface-200 bg-white"
                >
                    <span
                        class="w-2 h-2 rounded-full"
                        :class="
                            whatsappActive ? 'bg-emerald-500' : 'bg-red-500'
                        "
                    />

                    <span class="text-xs font-medium text-surface-700">
                        {{
                            whatsappActive
                                ? "WhatsApp Connected"
                                : "WhatsApp Not Connected"
                        }}
                    </span>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- Stats -->
            <!-- ========================================================= -->

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="stat in statCards"
                    :key="stat.label"
                    class="bg-white border border-surface-200 rounded-xl shadow-sm p-5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium text-surface-500">
                                {{ stat.label }}
                            </p>

                            <p
                                class="text-2xl font-semibold text-surface-900 mt-2"
                            >
                                {{ stat.value }}
                            </p>
                        </div>

                        <div
                            class="w-9 h-9 rounded-lg bg-surface-100 flex items-center justify-center"
                        >
                            <component
                                :is="stat.icon"
                                class="w-5 h-5 text-surface-600"
                            />
                        </div>
                    </div>

                    <p class="text-[11px] text-surface-400 mt-3 leading-4">
                        {{ stat.description }}
                    </p>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- Main Analytics -->
            <!-- ========================================================= -->

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Message Chart -->
                <div
                    class="xl:col-span-2 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2
                                    class="text-sm font-semibold text-surface-900"
                                >
                                    Message Activity
                                </h2>

                                <p class="text-xs text-surface-500 mt-1">
                                    Last 7 days
                                </p>
                            </div>

                            <!-- Legend -->
                            <div
                                class="flex items-center gap-4 text-[11px] text-surface-500"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    <span
                                        class="w-2.5 h-2.5 rounded-sm bg-blue-500"
                                    ></span>

                                    <span class="text-blue-600 font-medium">
                                        Inbound
                                    </span>
                                </span>

                                <span class="inline-flex items-center gap-1.5">
                                    <span
                                        class="w-2.5 h-2.5 rounded-sm bg-emerald-500"
                                    ></span>

                                    <span class="text-emerald-600 font-medium">
                                        Outbound
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div
                            v-if="chartData.length"
                            class="h-64 flex items-end gap-3 sm:gap-5"
                        >
                            <div
                                v-for="item in chartData"
                                :key="item.date"
                                class="flex-1 h-full flex flex-col justify-end min-w-0"
                            >
                                <!-- Bars -->
                                <div
                                    class="flex-1 flex items-end justify-center gap-1 sm:gap-1.5"
                                >
                                    <!-- Inbound -->
                                    <div
                                        class="w-1/2 max-w-8 flex flex-col justify-end items-center h-full"
                                    >
                                        <div
                                            class="w-full rounded-t-md bg-blue-500 transition-all"
                                            :style="{
                                                height:
                                                    chartBarHeight(
                                                        item.inbound,
                                                    ) + '%',
                                            }"
                                            :title="`Inbound: ${item.inbound}`"
                                        ></div>
                                    </div>

                                    <!-- Outbound -->
                                    <div
                                        class="w-1/2 max-w-8 flex flex-col justify-end items-center h-full"
                                    >
                                        <div
                                            class="w-full rounded-t-md bg-emerald-500 transition-all"
                                            :style="{
                                                height:
                                                    chartBarHeight(
                                                        item.outbound,
                                                    ) + '%',
                                            }"
                                            :title="`Outbound: ${item.outbound}`"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Date + Total -->
                                <div class="text-center mt-3">
                                    <p
                                        class="text-[10px] font-medium text-surface-600"
                                    >
                                        {{ formatChartDate(item.date) }}
                                    </p>

                                    <p
                                        class="text-[10px] text-surface-400 mt-0.5"
                                    >
                                        {{ item.total }} total
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Empty -->
                        <div
                            v-else
                            class="h-64 flex items-center justify-center"
                        >
                            <div class="text-center">
                                <MessagesSquare
                                    class="w-8 h-8 text-surface-300 mx-auto"
                                />

                                <p class="text-sm text-surface-500 mt-2">
                                    No message activity yet.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- Unread Messages -->
                <!-- ========================================================= -->

                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm overflow-hidden"
                >
                    <!-- Header -->

                    <div class="px-6 py-4 border-b border-surface-200">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2
                                        class="text-sm font-semibold text-surface-900"
                                    >
                                        Unread Messages
                                    </h2>

                                    <span
                                        v-if="unreadCount > 0"
                                        class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-red-100 text-red-700 text-[10px] font-semibold"
                                    >
                                        {{ formatNumber(unreadCount) }}
                                    </span>
                                </div>

                                <p class="text-xs text-surface-500 mt-1">
                                    Incoming conversations that still need
                                    attention.
                                </p>
                            </div>

                            <Link
                                :href="route('executive.messages.index')"
                                class="text-xs font-medium text-surface-700 hover:text-surface-900 whitespace-nowrap"
                            >
                                Open Inbox →
                            </Link>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[10px] text-surface-400">
                                {{
                                    loadingUnread
                                        ? "Updating..."
                                        : "Auto-updates every 10 seconds"
                                }}
                            </span>

                            <span
                                v-if="lastUnreadRefresh"
                                class="text-[10px] text-surface-400"
                            >
                                Synced {{ lastUnreadRefresh }}
                            </span>
                        </div>
                    </div>

                    <!-- Conversations -->

                    <div
                        v-if="unreadChats.length"
                        class="divide-y divide-surface-100"
                    >
                        <button
                            v-for="chat in unreadChats.slice(0, 5)"
                            :key="`${chat.customer_id}-${chat.whatsapp_number_id}`"
                            type="button"
                            @click="openUnreadChat(chat)"
                            class="w-full px-6 py-4 flex items-start gap-4 text-left hover:bg-surface-50 transition"
                        >
                            <!-- Avatar -->

                            <div
                                class="w-9 h-9 rounded-full bg-red-50 text-red-700 flex items-center justify-center flex-shrink-0 text-xs font-semibold"
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
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div
                                        class="flex items-center gap-2 min-w-0"
                                    >
                                        <p
                                            class="text-sm font-medium text-surface-900 truncate"
                                        >
                                            {{ chat.customer_name }}
                                        </p>

                                        <span
                                            class="text-[10px] px-1.5 py-0.5 rounded-full bg-red-50 text-red-700 flex-shrink-0"
                                        >
                                            Unread
                                        </span>
                                    </div>

                                    <span
                                        class="text-[10px] text-surface-400 whitespace-nowrap"
                                    >
                                        {{ chat.time_ago }}
                                    </span>
                                </div>

                                <!-- Message -->

                                <p
                                    class="text-xs text-surface-500 mt-1 truncate"
                                >
                                    {{ messagePreview(chat) }}
                                </p>

                                <!-- Masked phone -->

                                <p
                                    v-if="chat.customer_phone"
                                    class="text-[10px] text-surface-400 mt-1"
                                >
                                    {{ chat.customer_phone }}
                                </p>

                                <!-- Unread count -->

                                <p
                                    v-if="chat.unread_count > 1"
                                    class="text-[10px] text-red-600 mt-1"
                                >
                                    {{ chat.unread_count }} unread messages
                                </p>
                            </div>
                        </button>
                    </div>

                    <!-- Empty -->

                    <div v-else class="px-6 py-10 text-center">
                        <div
                            class="w-10 h-10 mx-auto rounded-full bg-emerald-50 flex items-center justify-center"
                        >
                            ✓
                        </div>

                        <p class="text-sm font-medium text-surface-900 mt-3">
                            All caught up
                        </p>

                        <p class="text-xs text-surface-500 mt-1">
                            No unread customer messages.
                        </p>
                    </div>

                    <!-- Sync information -->
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div>
                    <!-- WhatsApp Status -->
                    <div
                        class="bg-white border border-surface-200 rounded-xl shadow-sm"
                    >
                        <div class="px-6 py-4 border-b border-surface-200">
                            <h2 class="text-sm font-semibold text-surface-900">
                                WhatsApp
                            </h2>

                            <p class="text-xs text-surface-500 mt-1">
                                Current team connection
                            </p>
                        </div>

                        <div class="p-6">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center"
                                    :class="
                                        whatsappActive
                                            ? 'bg-emerald-50'
                                            : 'bg-red-50'
                                    "
                                >
                                    <CheckCircle2
                                        v-if="whatsappActive"
                                        class="w-5 h-5 text-emerald-600"
                                    />

                                    <XCircle
                                        v-else
                                        class="w-5 h-5 text-red-600"
                                    />
                                </div>

                                <div>
                                    <p
                                        class="text-sm font-semibold text-surface-900"
                                    >
                                        {{
                                            whatsappActive
                                                ? "Connected"
                                                : "Not Connected"
                                        }}
                                    </p>

                                    <p class="text-xs text-surface-500 mt-0.5">
                                        {{
                                            whatsappNumber?.display_phone_number ||
                                            "No number assigned"
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="whatsappNumber?.verified_name"
                                class="mt-5 pt-5 border-t border-surface-100"
                            >
                                <p
                                    class="text-[10px] uppercase tracking-wider text-surface-400"
                                >
                                    Verified Name
                                </p>

                                <p class="text-sm text-surface-700 mt-1">
                                    {{ whatsappNumber.verified_name }}
                                </p>
                            </div>

                            <div
                                v-if="whatsappNumber?.last_connected_at"
                                class="mt-4"
                            >
                                <p
                                    class="text-[10px] uppercase tracking-wider text-surface-400"
                                >
                                    Last Connected
                                </p>

                                <p class="text-xs text-surface-600 mt-1">
                                    {{
                                        formatDate(
                                            whatsappNumber.last_connected_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="xl:col-span-2 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <!-- ========================================================= -->
                    <!-- Recent Activity -->
                    <!-- ========================================================= -->
                    <div
                        class="bg-white border border-surface-200 rounded-xl shadow-sm overflow-hidden"
                    >
                        <div
                            class="px-6 py-4 border-b border-surface-200 flex items-center justify-between gap-4"
                        >
                            <div>
                                <h2
                                    class="text-sm font-semibold text-surface-900"
                                >
                                    Recent Activity
                                </h2>

                                <p class="text-xs text-surface-500 mt-1">
                                    Latest conversations from your customers.
                                </p>
                            </div>

                            <Link
                                :href="route('executive.messages.index')"
                                class="inline-flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-slate-900"
                            >
                                View Inbox

                                <ArrowRight class="w-3.5 h-3.5" />
                            </Link>
                        </div>

                        <div
                            v-if="recentMessages.length"
                            class="divide-y divide-surface-100"
                        >
                            <div
                                v-for="message in recentMessages"
                                :key="message.id"
                                class="px-6 py-4 hover:bg-surface-50 transition"
                            >
                                <div class="flex items-start gap-4">
                                    <!-- Direction -->
                                    <div
                                        class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
                                        :class="
                                            message.direction === 'inbound'
                                                ? 'bg-blue-50'
                                                : 'bg-emerald-50'
                                        "
                                    >
                                        <ArrowDownLeft
                                            v-if="
                                                message.direction === 'inbound'
                                            "
                                            class="w-4 h-4 text-blue-600"
                                        />

                                        <ArrowUpRight
                                            v-else
                                            class="w-4 h-4 text-emerald-600"
                                        />
                                    </div>

                                    <!-- Message -->
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <p
                                                    class="text-sm font-semibold text-surface-900"
                                                >
                                                    {{
                                                        message.customer
                                                            ?.name ||
                                                        "Unknown Customer"
                                                    }}
                                                </p>

                                                <span
                                                    v-if="
                                                        message.direction ===
                                                            'inbound' &&
                                                        !message.read_at
                                                    "
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-[10px] font-medium"
                                                >
                                                    Unread
                                                </span>
                                            </div>

                                            <span
                                                class="text-[11px] text-surface-400"
                                            >
                                                {{
                                                    message.time_ago ||
                                                    formatDate(
                                                        message.created_at,
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <p
                                            class="text-xs text-surface-500 mt-1 line-clamp-2"
                                        >
                                            {{ messagePreview(message) }}
                                        </p>

                                        <div
                                            class="flex flex-wrap items-center gap-2 mt-2"
                                        >
                                            <span
                                                class="text-[10px] text-surface-400 capitalize"
                                            >
                                                {{ message.type || "message" }}
                                            </span>

                                            <span
                                                v-if="message.has_document"
                                                class="text-[10px] text-surface-400"
                                            >
                                                • Attachment
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Customer -->
                                    <Link
                                        v-if="message.customer?.id"
                                        :href="
                                            route(
                                                'executive.customers.show',
                                                message.customer.id,
                                            )
                                        "
                                        class="shrink-0 text-xs font-medium text-slate-600 hover:text-slate-900"
                                    >
                                        View
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Empty -->
                        <div v-else class="px-6 py-12 text-center">
                            <MessagesSquare
                                class="w-9 h-9 text-surface-300 mx-auto"
                            />

                            <p
                                class="text-sm font-medium text-surface-700 mt-3"
                            >
                                No recent activity
                            </p>

                            <p class="text-xs text-surface-500 mt-1">
                                Messages from your customers will appear here.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- Quick Actions -->
            <!-- ========================================================= -->

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Link
                    :href="route('executive.customers.index')"
                    class="bg-white border border-surface-200 rounded-xl p-5 hover:border-slate-300 hover:shadow-sm transition"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-lg bg-surface-100 flex items-center justify-center"
                        >
                            <Users class="w-5 h-5 text-surface-600" />
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-surface-900">
                                My Customers
                            </p>

                            <p class="text-xs text-surface-500 mt-1">
                                View and manage your customers.
                            </p>
                        </div>
                    </div>
                </Link>

                <Link
                    :href="route('executive.messages.index')"
                    class="bg-white border border-surface-200 rounded-xl p-5 hover:border-slate-300 hover:shadow-sm transition"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-lg bg-surface-100 flex items-center justify-center"
                        >
                            <MessageCircle class="w-5 h-5 text-surface-600" />
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-surface-900">
                                WhatsApp Inbox
                            </p>

                            <p class="text-xs text-surface-500 mt-1">
                                Open your customer conversations.
                            </p>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </ExecutiveLayout>
</template>
