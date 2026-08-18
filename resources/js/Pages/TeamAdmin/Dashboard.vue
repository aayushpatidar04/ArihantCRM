<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";

import TeamAdminLayout from "@/Components/Layout/TeamAdminLayout.vue";

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },

    stats: {
        type: Object,
        default: () => ({
            total_customers: 0,
            active_customers: 0,
            unread_messages: 0,
            messages_today: 0,
            inbound_today: 0,
            outbound_today: 0,
            pending_documents: 0,
            total_documents: 0,
        }),
    },

    messageChart: {
        type: Array,
        default: () => [],
    },

    recentMessages: {
        type: Object,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            total: 0,
        }),
    },

    whatsappStatus: {
        type: Object,
        default: null,
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
        }),
    },
});

const page = usePage();

const workspace = computed(() => page.props.workspace ?? {});

const accessibleTeams = computed(() => workspace.value.teams ?? []);

const currentTeam = computed(() => workspace.value.current_team ?? null);

const whatsappNumber = computed(() => props.team?.whatsapp_number ?? null);

const search = ref(props.filters?.search ?? "");

const searching = ref(false);

const performSearch = () => {
    searching.value = true;

    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                searching.value = false;
            },
        },
    );
};

const clearSearch = () => {
    search.value = "";

    performSearch();
};

const maxChartValue = computed(() => {
    const values = props.messageChart.map((item) => Number(item.total) || 0);

    return Math.max(1, ...values);
});

const chartHeight = (value) => {
    const number = Number(value) || 0;

    if (!number) {
        return 4;
    }

    return Math.max(8, Math.round((number / maxChartValue.value) * 100));
};

const formatNumber = (value) => {
    return new Intl.NumberFormat("en-IN").format(Number(value ?? 0));
};

const formatTime = (value) => {
    if (!value) {
        return "—";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleTimeString("en-IN", {
        hour: "2-digit",
        minute: "2-digit",
    });
};

const formatDateTime = (value) => {
    if (!value) {
        return "—";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString("en-IN", {
        day: "2-digit",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const messagePreview = (message) => {
    if (message.body) {
        return message.body;
    }

    if (message.type === "image") {
        return "Image";
    }

    if (message.type === "video") {
        return "Video";
    }

    if (message.type === "audio") {
        return "Audio";
    }

    if (message.type === "document") {
        return "Document";
    }

    if (message.has_document) {
        return "Media attachment";
    }

    return "Message";
};

const messageTypeLabel = (message) => {
    if (!message) {
        return "";
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

        case "chat":
            return "Message";

        default:
            return message.type
                ? message.type.charAt(0).toUpperCase() + message.type.slice(1)
                : "Message";
    }
};

const whatsappIsActive = computed(() =>
    Boolean(props.whatsappStatus?.is_active ?? whatsappNumber.value?.is_active),
);

const whatsappStatusLabel = computed(() => {
    if (!props.whatsappStatus && !whatsappNumber.value) {
        return "Not configured";
    }

    return whatsappIsActive.value ? "Active" : "Inactive";
});

const recentActivity = computed(() => props.recentMessages?.data ?? []);

const totalRecentMessages = computed(() =>
    Number(props.recentMessages?.total ?? 0),
);

const goToPage = (pageNumber) => {
    if (
        pageNumber < 1 ||
        pageNumber > Number(props.recentMessages?.last_page ?? 1)
    ) {
        return;
    }

    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
            page: pageNumber,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const unreadChats = ref([]);
const unreadChatCount = ref(0);
const unreadCount = ref(Number(props.stats?.unread_messages ?? 0));

const loadingUnread = ref(false);

const loadUnreadMessages = async () => {
    loadingUnread.value = true;

    try {
        const response = await fetch("/team-admin/dashboard/unread-messages", {
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
    } catch (error) {
        console.error("Failed to load unread messages:", error);
    } finally {
        loadingUnread.value = false;
    }
};

let pollInterval = null;
const POLL_INTERVAL_MS = 10_000;

onMounted(() => {
    // Initial fetch
    loadUnreadMessages();
    // Start polling
    pollInterval = setInterval(loadUnreadMessages, POLL_INTERVAL_MS);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <TeamAdminLayout title="Dashboard">
        <div class="space-y-6">
            <!-- ========================================================= -->
            <!-- Header -->
            <!-- ========================================================= -->

            <div
                class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4"
            >
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        Team Dashboard
                    </h1>

                    <p class="mt-1 text-sm text-surface-500">
                        Manage your current team workspace and its WhatsApp
                        operations.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium"
                        :class="
                            whatsappIsActive
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-red-50 text-red-700'
                        "
                    >
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="
                                whatsappIsActive
                                    ? 'bg-emerald-500'
                                    : 'bg-red-500'
                            "
                        ></span>

                        WhatsApp:
                        {{ whatsappStatusLabel }}
                    </span>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- Analytics Cards -->
            <!-- ========================================================= -->

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <!-- Customers -->

                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm p-5"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold text-surface-500">
                                Total Customers
                            </p>

                            <p
                                class="mt-2 text-2xl font-semibold text-surface-900"
                            >
                                {{ formatNumber(stats.total_customers) }}
                            </p>
                        </div>

                        <div
                            class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center"
                        >
                            <span class="text-lg"> 👥 </span>
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-surface-500">
                        {{ formatNumber(stats.active_customers) }}
                        active customers
                    </p>
                </div>

                <!-- ========================================================= -->
                <!-- Unread Messages -->
                <!-- ========================================================= -->

                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2
                                        class="text-xs font-semibold text-surface-900"
                                    >
                                        Unread Messages
                                    </h2>

                                    <span
                                        v-if="unreadCount > 0"
                                        class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-red-100 text-red-700 text-md font-semibold"
                                    >
                                        {{ formatNumber(unreadCount) }}
                                    </span>
                                </div>

                                <p class="text-xs text-surface-500 mt-1">
                                    <span class="text-2xl">{{ unreadChatCount }}</span>
                                    conversation{{
                                        unreadChatCount === 1 ? "" : "s"
                                    }}
                                    waiting for attention.
                                </p>
                            </div>

                        </div>
                        <Link
                            :href="route('team-admin.messages.index')"
                            class="text-xs font-medium text-surface-700 p-2 bg-gray-100 rounded-xl hover:text-surface-900 hover:bg-gray-300"
                        >
                            Open Inbox →
                        </Link>
                    </div>
                </div>

                <!-- Messages Today -->
                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm p-5"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold text-surface-500">
                                Messages Today
                            </p>

                            <p
                                class="mt-2 text-2xl font-semibold text-surface-900"
                            >
                                {{ formatNumber(stats.messages_today) }}
                            </p>
                        </div>

                        <div
                            class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center"
                        >
                            <span class="text-lg"> 📊 </span>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-3 text-xs">
                        <span class="text-emerald-600">
                            ↓
                            {{ formatNumber(stats.inbound_today) }}
                            inbound
                        </span>

                        <span class="text-blue-600">
                            ↑
                            {{ formatNumber(stats.outbound_today) }}
                            outbound
                        </span>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-3 gap-2">
                <div class="col-span-2">
                    <!-- ========================================================= -->
                    <!-- Message Analytics -->
                    <!-- ========================================================= -->

                    <div
                        class="bg-white border border-surface-200 rounded-xl shadow-sm mb-2"
                    >
                        <div class="px-6 py-4 border-b border-surface-200">
                            <div
                                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
                            >
                                <div>
                                    <h2
                                        class="text-sm font-semibold text-surface-900"
                                    >
                                        Message Analytics
                                    </h2>

                                    <p class="text-xs text-surface-500 mt-1">
                                        Incoming and outgoing messages over the
                                        last 7 days.
                                    </p>
                                </div>

                                <div class="flex items-center gap-4 text-xs">
                                    <span
                                        class="flex items-center gap-1.5 text-surface-600"
                                    >
                                        <span
                                            class="w-2 h-2 rounded-full bg-surface-400"
                                        ></span>
                                        Total
                                    </span>

                                    <span
                                        class="flex items-center gap-1.5 text-emerald-600"
                                    >
                                        <span
                                            class="w-2 h-2 rounded-full bg-emerald-500"
                                        ></span>
                                        Inbound
                                    </span>

                                    <span
                                        class="flex items-center gap-1.5 text-blue-600"
                                    >
                                        <span
                                            class="w-2 h-2 rounded-full bg-blue-500"
                                        ></span>
                                        Outbound
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <div
                                v-if="messageChart.length"
                                class="h-64 flex items-end gap-3 md:gap-5"
                            >
                                <div
                                    v-for="item in messageChart"
                                    :key="item.date"
                                    class="flex-1 h-full flex flex-col justify-end"
                                >
                                    <div
                                        class="flex-1 flex items-end justify-center gap-1"
                                    >
                                        <!-- Inbound -->

                                        <div
                                            class="w-1/3 max-w-8 bg-emerald-500 rounded-t-md transition-all"
                                            :style="{
                                                height:
                                                    chartHeight(item.inbound) +
                                                    '%',
                                            }"
                                            :title="item.inbound + ' inbound'"
                                        ></div>

                                        <!-- Outbound -->

                                        <div
                                            class="w-1/3 max-w-8 bg-blue-500 rounded-t-md transition-all"
                                            :style="{
                                                height:
                                                    chartHeight(item.outbound) +
                                                    '%',
                                            }"
                                            :title="item.outbound + ' outbound'"
                                        ></div>
                                    </div>

                                    <div class="mt-3 text-center">
                                        <p
                                            class="text-[11px] font-medium text-surface-600"
                                        >
                                            {{ item.label }}
                                        </p>

                                        <p
                                            class="text-[10px] text-surface-400 mt-0.5"
                                        >
                                            {{ item.total }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="py-16 text-center">
                                <p class="text-sm text-surface-500">
                                    No message activity available.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- Recent Activity -->
                    <!-- ========================================================= -->

                    <div
                        class="bg-white border border-surface-200 rounded-xl shadow-sm"
                    >
                        <!-- Header -->

                        <div class="px-6 py-4 border-b border-surface-200">
                            <div
                                class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4"
                            >
                                <div>
                                    <h2
                                        class="text-sm font-semibold text-surface-900"
                                    >
                                        Recent Activity
                                    </h2>

                                    <p class="text-xs text-surface-500 mt-1">
                                        Latest activity across your team
                                        conversations.
                                    </p>
                                </div>

                                <!-- Search -->

                                <form
                                    @submit.prevent="performSearch"
                                    class="flex items-center gap-2 w-full lg:w-auto"
                                >
                                    <div class="relative w-full sm:w-72">
                                        <input
                                            v-model="search"
                                            type="text"
                                            placeholder="Search customer or message..."
                                            class="w-full rounded-lg border border-surface-200 bg-white px-3 py-2 pr-8 text-xs text-surface-900 placeholder:text-surface-400 focus:outline-none focus:ring-2 focus:ring-surface-200"
                                        />

                                        <button
                                            v-if="search"
                                            type="button"
                                            @click="clearSearch"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-700"
                                        >
                                            ×
                                        </button>
                                    </div>

                                    <button
                                        type="submit"
                                        :disabled="searching"
                                        class="px-3 py-2 rounded-lg bg-surface-900 text-white text-xs font-medium hover:bg-surface-800 disabled:opacity-50"
                                    >
                                        {{ searching ? "..." : "Search" }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Activity List -->

                        <div
                            v-if="recentActivity.length"
                            class="divide-y divide-surface-100"
                        >
                            <div
                                v-for="message in recentActivity"
                                :key="message.id"
                                class="px-6 py-4 hover:bg-surface-50 transition"
                            >
                                <a :href="route('team-admin.messages.show', message.customer_id)">
                                    <div class="flex items-start gap-4">
                                        <!-- Avatar -->
    
                                        <div
                                            class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-semibold"
                                            :class="
                                                message.direction === 'inbound'
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-blue-50 text-blue-700'
                                            "
                                        >
                                            {{
                                                message.customer_name
                                                    ?.charAt(0)
                                                    ?.toUpperCase() || "?"
                                            }}
                                        </div>
    
                                        <!-- Content -->
    
                                        <div class="min-w-0 flex-1">
                                            <!-- Top -->
    
                                            <div
                                                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1"
                                            >
                                                <div
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    <p
                                                        class="text-sm font-medium text-surface-900"
                                                    >
                                                        {{ message.customer_name }}
                                                    </p>
    
                                                    <span
                                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
                                                        :class="
                                                            message.direction ===
                                                            'inbound'
                                                                ? 'bg-emerald-50 text-emerald-700'
                                                                : 'bg-blue-50 text-blue-700'
                                                        "
                                                    >
                                                        {{
                                                            message.direction ===
                                                            "inbound"
                                                                ? "Incoming"
                                                                : "Outgoing"
                                                        }}
                                                    </span>
    
                                                    <span
                                                        class="inline-flex items-center px-1.5 py-0.5 rounded bg-surface-100 text-surface-500 text-[10px]"
                                                    >
                                                        {{
                                                            messageTypeLabel(
                                                                message,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
    
                                                <span
                                                    class="text-[10px] text-surface-400 whitespace-nowrap"
                                                >
                                                    {{
                                                        message.time_ago ||
                                                        formatDateTime(
                                                            message.created_at,
                                                        )
                                                    }}
                                                </span>
                                            </div>
    
                                            <!-- Phone -->
    
                                            <p
                                                v-if="message.customer_phone"
                                                class="text-[10px] text-surface-400 mt-1"
                                            >
                                                {{ message.customer_phone }}
                                            </p>
    
                                            <!-- Message -->
    
                                            <p
                                                class="text-xs text-surface-600 mt-2 line-clamp-2"
                                            >
                                                {{ messagePreview(message) }}
                                            </p>
    
                                            <!-- Meta -->
    
                                            <div
                                                class="flex flex-wrap items-center gap-3 mt-2"
                                            >
                                                <span
                                                    v-if="message.sent_by"
                                                    class="text-[10px] text-surface-400"
                                                >
                                                    By:
                                                    {{ message.sent_by }}
                                                </span>
    
                                                <span
                                                    v-if="message.whatsapp_number"
                                                    class="text-[10px] text-surface-400"
                                                >
                                                    WhatsApp:
                                                    {{ message.whatsapp_number }}
                                                </span>
    
                                                <span
                                                    v-if="message.has_document"
                                                    class="text-[10px] text-purple-600"
                                                >
                                                    📎 Attachment
                                                </span>
                                            </div>
    
                                            <!-- Document -->
    
                                            <div
                                                v-if="message.document"
                                                class="mt-3 inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-surface-50 border border-surface-200"
                                            >
                                                <span class="text-xs"> 📄 </span>
    
                                                <div>
                                                    <p
                                                        class="text-[11px] font-medium text-surface-700"
                                                    >
                                                        {{
                                                            message.document
                                                                .filename
                                                        }}
                                                    </p>
    
                                                    <p
                                                        class="text-[10px] text-surface-400"
                                                    >
                                                        {{ message.document.size }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Empty -->

                        <div v-else class="px-6 py-12 text-center">
                            <p class="text-sm text-surface-500">
                                {{
                                    search
                                        ? "No activity found for your search."
                                        : "No recent activity yet."
                                }}
                            </p>
                        </div>

                        <!-- Pagination -->

                        <div
                            v-if="Number(recentMessages.last_page) > 1"
                            class="px-6 py-4 border-t border-surface-200 flex items-center justify-between"
                        >
                            <p class="text-xs text-surface-500">
                                Showing page
                                {{ recentMessages.current_page }}
                                of
                                {{ recentMessages.last_page }}
                            </p>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="
                                        goToPage(
                                            Number(
                                                recentMessages.current_page,
                                            ) - 1,
                                        )
                                    "
                                    :disabled="
                                        Number(recentMessages.current_page) <= 1
                                    "
                                    class="px-3 py-1.5 rounded-lg border border-surface-200 text-xs text-surface-600 disabled:opacity-40 hover:bg-surface-50"
                                >
                                    Previous
                                </button>

                                <button
                                    type="button"
                                    @click="
                                        goToPage(
                                            Number(
                                                recentMessages.current_page,
                                            ) + 1,
                                        )
                                    "
                                    :disabled="
                                        Number(recentMessages.current_page) >=
                                        Number(recentMessages.last_page)
                                    "
                                    class="px-3 py-1.5 rounded-lg border border-surface-200 text-xs text-surface-600 disabled:opacity-40 hover:bg-surface-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>

                        <div v-if="totalRecentMessages" class="px-6 pb-4">
                            <p class="text-[10px] text-surface-400">
                                {{ formatNumber(totalRecentMessages) }}
                                total recent conversation records
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- Unread Messages -->
                <!-- ========================================================= -->

                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                        >
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
                                        {{
                                            formatNumber(unreadCount)
                                        }}
                                    </span>
                                </div>

                                <p class="text-xs text-surface-500 mt-1">
                                    Incoming conversations that still need
                                    attention.
                                </p>
                            </div>

                            <Link
                                :href="route('team-admin.messages.index')"
                                class="text-xs font-medium text-surface-700 hover:text-surface-900"
                            >
                                Open Inbox →
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="unreadCount > 0"
                        class="divide-y divide-surface-100"
                    >
                        <div
                            v-for="message in unreadChats.slice(0, 5)"
                            :key="message.id"
                        >
                            <a class="px-6 py-4 flex items-start gap-4" :href="route('team-admin.messages.show', message.customer_id)">
                                <div
                                    class="w-9 h-9 rounded-full bg-red-50 text-red-700 flex items-center justify-center flex-shrink-0 text-xs font-semibold"
                                >
                                    {{
                                        message.customer_name
                                            ?.charAt(0)
                                            ?.toUpperCase() || "?"
                                    }}
                                </div>
    
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
                                                {{ message.customer_name }}
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
                                            {{ formatTime(message.created_at) }}
                                        </span>
                                    </div>
    
                                    <p
                                        class="text-xs text-surface-500 mt-1 truncate"
                                    >
                                        {{ messagePreview(message) }}
                                    </p>
    
                                    <p
                                        v-if="message.customer_phone"
                                        class="text-[10px] text-surface-400 mt-1"
                                    >
                                        {{ message.customer_phone }}
                                    </p>
                                </div>
                            </a>
                        </div>
                    </div>

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
                            There are no unread messages.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- Workspace -->
            <!-- ========================================================= -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Current Workspace
                    </h2>
                </div>

                <div
                    class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"
                >
                    <!-- Current Team -->

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Current Team
                        </p>

                        <p class="text-sm font-semibold text-surface-900">
                            {{ currentTeam?.name || "—" }}
                        </p>

                        <p class="text-xs text-surface-400 mt-1">
                            Team ID:
                            {{ currentTeam?.id || "—" }}
                        </p>
                    </div>

                    <!-- User Team -->

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Primary Team ID
                        </p>

                        <p class="text-sm font-semibold text-surface-900">
                            {{ page.props.auth?.user?.team_id || "—" }}
                        </p>
                    </div>

                    <!-- Accessible Teams -->

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            Accessible Teams
                        </p>

                        <p class="text-sm font-semibold text-surface-900">
                            {{ accessibleTeams.length }}
                        </p>
                    </div>

                    <!-- WhatsApp -->

                    <div>
                        <p class="text-xs text-surface-500 mb-1">
                            WhatsApp Number
                        </p>

                        <p
                            v-if="whatsappNumber"
                            class="text-sm font-semibold text-surface-900"
                        >
                            {{
                                whatsappNumber.display_phone_number ||
                                whatsappNumber.phone_number
                            }}
                        </p>

                        <p v-else class="text-sm text-surface-500">
                            Not assigned
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- WhatsApp Status -->
            <!-- ========================================================= -->

            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        WhatsApp Connection
                    </h2>

                    <p class="text-xs text-surface-500 mt-1">
                        Current WhatsApp number and connection information.
                    </p>
                </div>

                <div class="p-6">
                    <div
                        v-if="whatsappStatus"
                        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"
                    >
                        <div>
                            <p class="text-xs text-surface-500 mb-1">Number</p>

                            <p class="text-sm font-semibold text-surface-900">
                                {{
                                    whatsappStatus.display_phone_number ||
                                    whatsappStatus.phone_number ||
                                    "—"
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Business Name
                            </p>

                            <p class="text-sm font-semibold text-surface-900">
                                {{ whatsappStatus.verified_name || "—" }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Last Connected
                            </p>

                            <p class="text-sm font-medium text-surface-700">
                                {{
                                    formatDateTime(
                                        whatsappStatus.last_connected_at,
                                    )
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500 mb-1">
                                Last Webhook
                            </p>

                            <p class="text-sm font-medium text-surface-700">
                                {{
                                    formatDateTime(
                                        whatsappStatus.last_webhook_at,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div v-else class="py-6 text-center">
                        <p class="text-sm text-surface-500">
                            No WhatsApp number is assigned to this team.
                        </p>
                    </div>

                    <div
                        v-if="whatsappStatus?.last_connection_error"
                        class="mt-5 p-3 rounded-lg bg-red-50 border border-red-100"
                    >
                        <p class="text-xs font-medium text-red-700">
                            Latest connection error
                        </p>

                        <p class="text-xs text-red-600 mt-1">
                            {{ whatsappStatus.last_connection_error }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- Workspace Access -->
            <!-- ========================================================= -->

            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Workspace Access
                    </h2>

                    <p class="text-xs text-surface-500 mt-1">
                        Teams available to this Team Admin.
                    </p>
                </div>

                <div class="divide-y divide-surface-100">
                    <div
                        v-for="team in accessibleTeams"
                        :key="team.id"
                        class="px-6 py-4 flex items-center justify-between gap-4"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-surface-900">
                                    {{ team.name }}
                                </p>

                                <span
                                    v-if="
                                        Number(team.id) ===
                                        Number(currentTeam?.id)
                                    "
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-50 text-emerald-700"
                                >
                                    Current
                                </span>
                            </div>

                            <p class="text-xs text-surface-500 mt-1">
                                Team ID: {{ team.id }}
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-xs text-surface-500">WhatsApp</p>

                            <p class="text-xs font-medium text-surface-700">
                                {{
                                    team.whatsapp_number
                                        ?.display_phone_number ||
                                    team.whatsapp_number?.phone_number ||
                                    "Not assigned"
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="!accessibleTeams.length"
                        class="px-6 py-8 text-center"
                    >
                        <p class="text-sm text-surface-500">
                            No accessible teams found.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- Quick Actions -->
            <!-- ========================================================= -->

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Link
                    :href="route('team-admin.customers.index')"
                    class="bg-white border border-surface-200 rounded-xl p-5 hover:border-slate-300 hover:shadow-sm transition"
                >
                    <p class="text-sm font-semibold text-surface-900">
                        Customers
                    </p>

                    <p class="text-xs text-surface-500 mt-1">
                        Manage customers for this workspace.
                    </p>
                </Link>

                <Link
                    :href="route('team-admin.messages.index')"
                    class="bg-white border border-surface-200 rounded-xl p-5 hover:border-slate-300 hover:shadow-sm transition"
                >
                    <p class="text-sm font-semibold text-surface-900">
                        WhatsApp Inbox
                    </p>

                    <p class="text-xs text-surface-500 mt-1">
                        View conversations for this team.
                    </p>
                </Link>

                <Link
                    :href="route('team-admin.users.index')"
                    class="bg-white border border-surface-200 rounded-xl p-5 hover:border-slate-300 hover:shadow-sm transition"
                >
                    <p class="text-sm font-semibold text-surface-900">
                        Team Users
                    </p>

                    <p class="text-xs text-surface-500 mt-1">
                        Manage users belonging to this team.
                    </p>
                </Link>
            </div>
        </div>
    </TeamAdminLayout>
</template>
