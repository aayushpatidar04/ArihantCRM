<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    /**
     * Laravel paginator from MessageController@index().
     *
     * Expected:
     * {
     *   data: [],
     *   current_page: 1,
     *   last_page: 1,
     *   next_page_url: null,
     *   ...
     * }
     */
    customers: {
        type: Object,
        required: true,
    },

    /**
     * Currently opened customer ID.
     */
    activeCustomerId: {
        type: [Number, String, null],
        default: null,
    },

    /**
     * Initial search value from Laravel.
     */
    initialSearch: {
        type: String,
        default: "",
    },

    searchRoute: {
        type: String,
        default: "executive.messages.index",
    },

    showRoute: {
        type: String,
        default: "executive.messages.show",
    },
});

const emit = defineEmits(["customer-changed"]);

const search = ref(props.initialSearch || "");
const searchTimeout = ref(null);

const customerList = computed(() => {
    return props.customers?.data || [];
});

const hasMorePages = computed(() => {
    return !!props.customers?.next_page_url;
});

/**
 * -------------------------------------------------------------
 * Search
 * -------------------------------------------------------------
 */
function handleSearch() {
    clearTimeout(searchTimeout.value);

    searchTimeout.value = setTimeout(() => {
        router.get(
            route(props.searchRoute, props.activeCustomerId),
            {
                search: search.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ["customers", "filters"],
            },
        );
    }, 400);
}

function clearSearch() {
    search.value = "";

    router.get(
        route(props.searchRoute, props.activeCustomerId),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ["customers", "filters"],
        },
    );
}

/**
 * -------------------------------------------------------------
 * Open conversation
 * -------------------------------------------------------------
 */
function openCustomer(customer) {
    if (!customer?.id) {
        return;
    }

    if (Number(customer.id) === Number(props.activeCustomerId)) {
        return;
    }

    emit("customer-changed", customer);

    router.visit(route(props.showRoute, customer.id), {
        preserveScroll: true,
    });
}

/**
 * -------------------------------------------------------------
 * Load more customers
 * -------------------------------------------------------------
 */
function loadMoreCustomers() {
    if (!props.customers?.next_page_url) {
        return;
    }

    router.get(
        props.customers.next_page_url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ["customers"],
        },
    );
}

/**
 * -------------------------------------------------------------
 * Customer display helpers
 * -------------------------------------------------------------
 */
function getInitials(name) {
    if (!name) {
        return "?";
    }

    const words = name.trim().split(/\s+/).filter(Boolean);

    if (!words.length) {
        return "?";
    }

    if (words.length === 1) {
        return words[0].substring(0, 2).toUpperCase();
    }

    return (words[0][0] + words[words.length - 1][0]).toUpperCase();
}

function getLatestMessage(customer) {
    if (!customer?.messages?.length) {
        return null;
    }

    return customer.messages[0];
}

function getMessagePreview(customer) {
    const message = getLatestMessage(customer);

    if (!message) {
        return "No messages yet";
    }

    const prefix = message.direction === "outbound" ? "You: " : "";

    switch (message.type) {
        case "image":
            return prefix + "📷 Photo";

        case "video":
            return prefix + "🎥 Video";

        case "audio":
            return prefix + "🎵 Audio";

        case "document":
            return prefix + "📎 Document";

        case "template":
            return prefix + "📋 Template";

        default:
            return prefix + (message.body || "Message");
    }
}

function getMessageTime(customer) {
    const message = getLatestMessage(customer);

    if (!message?.created_at) {
        return "";
    }

    return formatSidebarTime(message.created_at);
}

function formatSidebarTime(dateValue) {
    if (!dateValue) {
        return "";
    }

    const date = new Date(dateValue);

    if (Number.isNaN(date.getTime())) {
        return "";
    }

    const now = new Date();

    const isToday =
        date.getDate() === now.getDate() &&
        date.getMonth() === now.getMonth() &&
        date.getFullYear() === now.getFullYear();

    if (isToday) {
        return date.toLocaleTimeString([], {
            hour: "2-digit",
            minute: "2-digit",
        });
    }

    const yesterday = new Date();
    yesterday.setDate(now.getDate() - 1);

    const isYesterday =
        date.getDate() === yesterday.getDate() &&
        date.getMonth() === yesterday.getMonth() &&
        date.getFullYear() === yesterday.getFullYear();

    if (isYesterday) {
        return "Yesterday";
    }

    const daysDifference = Math.floor(
        (new Date(now.getFullYear(), now.getMonth(), now.getDate()) -
            new Date(date.getFullYear(), date.getMonth(), date.getDate())) /
            (1000 * 60 * 60 * 24),
    );

    if (daysDifference >= 0 && daysDifference < 7) {
        return date.toLocaleDateString([], {
            weekday: "short",
        });
    }

    return date.toLocaleDateString([], {
        day: "2-digit",
        month: "short",
    });
}

/**
 * -------------------------------------------------------------
 * Keep search prop synced
 * -------------------------------------------------------------
 */
watch(
    () => props.initialSearch,
    (value) => {
        if (value !== search.value) {
            search.value = value || "";
        }
    },
);
</script>

<template>
    <aside
        class="flex h-full w-full flex-col overflow-hidden border-r border-gray-200 bg-white"
    >
        <!-- =====================================================
             HEADER
        ====================================================== -->
        <div
            class="flex shrink-0 items-center justify-between border-b border-gray-100 px-4 py-4"
        >
            <div class="min-w-0">
                <h2 class="truncate text-lg font-semibold text-gray-900">
                    Messages
                </h2>

                <p class="mt-0.5 text-xs text-gray-500">
                    {{ customerList.length }}
                    {{
                        customerList.length === 1
                            ? "conversation"
                            : "conversations"
                    }}
                </p>
            </div>

            <div
                class="flex h-9 w-9 items-center justify-center rounded-full bg-green-50"
                title="WhatsApp Messages"
            >
                <svg
                    class="h-5 w-5 text-green-600"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        d="M12.04 2C6.5 2 2 6.5 2 12.04c0 1.77.46 3.5 1.33 5.02L2 22l5.09-1.31A10.01 10.01 0 0012.04 22C17.57 22 22 17.5 22 11.96S17.57 2 12.04 2zm5.84 14.19c-.25.7-1.25 1.28-1.98 1.44-.53.11-1.22.2-3.55-.76-2.98-1.23-4.9-4.25-5.05-4.45-.14-.2-1.2-1.6-1.2-3.05 0-1.45.76-2.16 1.03-2.45.27-.29.58-.36.78-.36.2 0 .39 0 .56.01.18.01.42-.07.66.51.25.6.85 2.07.92 2.22.08.15.13.33.02.53-.11.2-.16.33-.31.5-.15.18-.32.39-.45.52-.15.15-.31.31-.13.61.18.3.8 1.32 1.72 2.13 1.18 1.05 2.18 1.38 2.49 1.53.31.15.49.13.67-.08.18-.22.77-.9.98-1.21.2-.3.41-.25.69-.15.29.1 1.82.86 2.13 1.02.31.15.52.23.59.36.07.13.07.75-.18 1.45z"
                    />
                </svg>
            </div>
        </div>

        <!-- =====================================================
             SEARCH
        ====================================================== -->
        <div class="shrink-0 border-b border-gray-100 bg-white p-3">
            <div class="relative">
                <!-- Search icon -->
                <svg
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-3.5-3.5" />
                </svg>

                <input
                    v-model="search"
                    type="text"
                    placeholder="Search conversations..."
                    class="h-10 w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-green-500 focus:bg-white focus:ring-2 focus:ring-green-100"
                    @input="handleSearch"
                />

                <button
                    v-if="search"
                    type="button"
                    class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-700"
                    @click="clearSearch"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- =====================================================
             CONVERSATION LIST
        ====================================================== -->
        <div class="flex-1 overflow-y-auto">
            <!-- Empty state -->
            <div
                v-if="!customerList.length"
                class="flex min-h-full flex-col items-center justify-center px-6 py-12 text-center"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100"
                >
                    <svg
                        class="h-7 w-7 text-gray-400"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"
                        />
                    </svg>
                </div>

                <p class="mt-4 text-sm font-medium text-gray-700">
                    No conversations found
                </p>

                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                    {{
                        search
                            ? "Try searching with another name, phone or email."
                            : "Your assigned customer conversations will appear here."
                    }}
                </p>
            </div>

            <!-- Customer rows -->
            <button
                v-for="customer in customerList"
                :key="customer.id"
                type="button"
                class="group relative flex w-full items-center gap-3 border-b border-gray-100 px-4 py-3 text-left transition hover:bg-gray-50"
                :class="{
                    'bg-green-50 hover:bg-green-50':
                        Number(customer.id) === Number(activeCustomerId),
                }"
                @click="openCustomer(customer)"
            >
                <!-- Active indicator -->
                <span
                    v-if="Number(customer.id) === Number(activeCustomerId)"
                    class="absolute bottom-0 left-0 top-0 w-1 rounded-r-full bg-green-600"
                />

                <!-- Avatar -->
                <div
                    class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-emerald-600 text-sm font-semibold text-white shadow-sm"
                >
                    {{ getInitials(customer.name) }}

                    <!-- Unread indicator -->
                    <span
                        v-if="Number(customer.unread_count || 0) > 0"
                        class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-green-500"
                    />
                </div>

                <!-- Customer / Message -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <h3
                            class="truncate text-sm font-semibold"
                            :class="{
                                'text-gray-900':
                                    Number(customer.unread_count || 0) === 0,
                                'text-gray-950':
                                    Number(customer.unread_count || 0) > 0,
                            }"
                        >
                            {{ customer.name || "Unknown Customer" }}
                        </h3>

                        <span
                            class="shrink-0 text-[11px]"
                            :class="{
                                'font-medium text-green-600':
                                    Number(customer.unread_count || 0) > 0,
                                'text-gray-400':
                                    Number(customer.unread_count || 0) === 0,
                            }"
                        >
                            {{ getMessageTime(customer) }}
                        </span>
                    </div>

                    <div class="mt-1 flex items-center gap-2">
                        <p
                            class="min-w-0 flex-1 truncate text-xs"
                            :class="{
                                'font-medium text-gray-800':
                                    Number(customer.unread_count || 0) > 0,
                                'text-gray-500':
                                    Number(customer.unread_count || 0) === 0,
                            }"
                        >
                            {{ getMessagePreview(customer) }}
                        </p>

                        <!-- Unread count -->
                        <span
                            v-if="Number(customer.unread_count || 0) > 0"
                            class="flex min-w-[20px] shrink-0 items-center justify-center rounded-full bg-green-600 px-1.5 py-0.5 text-[10px] font-semibold text-white"
                        >
                            {{
                                Number(customer.unread_count) > 99
                                    ? "99+"
                                    : customer.unread_count
                            }}
                        </span>
                    </div>
                </div>
            </button>

            <!-- Load more -->
            <div v-if="hasMorePages" class="border-t border-gray-100 p-3">
                <button
                    type="button"
                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                    @click="loadMoreCustomers"
                >
                    Load more conversations
                </button>
            </div>
        </div>
    </aside>
</template>