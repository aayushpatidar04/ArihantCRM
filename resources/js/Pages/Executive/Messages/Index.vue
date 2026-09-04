<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { usePrivateChannel } from "@/Composables/useEcho";

import ExecutiveLayout from "@/Components/Layout/ExecutiveLayout.vue";

const props = defineProps({
    customers: {
        type: Object,
        required: true,
    },

    currentTeam: {
        type: Object,
        default: null,
    },

    filters: {
        type: Object,
        default: () => ({}),
    },
});

const customerList = ref([...(props.customers?.data ?? [])]);

watch(
    () => props.customers?.data,
    (data) => {
        customerList.value = [...(data ?? [])];
    },
    {
        deep: true,
    },
);

const customers = computed(() => customerList.value);

const teamId = computed(() => props.currentTeam?.id ?? null);

/*
|--------------------------------------------------------------------------
| Realtime
|--------------------------------------------------------------------------
*/

usePrivateChannel(`whatsapp.team.${teamId.value}`, {
    "message.received": (event) => {
        const message = event?.message;

        if (!message) {
            return;
        }

        if (!message.customer_id) {
            return;
        }

        const index = customerList.value.findIndex(
            (customer) => Number(customer.id) === Number(message.customer_id),
        );

        /*
            |--------------------------------------------------------------------------
            | Existing conversation
            |--------------------------------------------------------------------------
            */

        if (index !== -1) {
            const existing = customerList.value[index];

            const isInbound = message.direction === "inbound";

            const updated = {
                ...existing,

                messages: [message],

                unread_count:
                    Number(existing.unread_count ?? 0) + (isInbound ? 1 : 0),
            };

            customerList.value.splice(index, 1);

            customerList.value.unshift(updated);

            return;
        }

        /*
            |--------------------------------------------------------------------------
            | Not in current page
            |--------------------------------------------------------------------------
            */

        router.reload({
            only: ["customers"],

            preserveScroll: true,
            preserveState: true,
        });
    },
});

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

const search = ref(props.filters?.search ?? "");

const applySearch = () => {
    router.get(
        route("executive.messages.index"),
        {
            search: search.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const whatsappNumber = computed(
    () => props.currentTeam?.whatsapp_number ?? null,
);

const formatTime = (value) => {
    if (!value) {
        return "";
    }

    return new Date(value).toLocaleString("en-IN", {
        day: "2-digit",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
    });
};
</script>

<template>
    <Head title="WhatsApp Inbox" />

    <ExecutiveLayout title="WhatsApp Inbox">
        <div class="space-y-5">
            <!-- Header -->

            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        WhatsApp Inbox
                    </h1>

                    <p class="text-sm text-surface-500 mt-1">
                        Conversations assigned to you or previously owned by
                        you.
                    </p>
                </div>

                <div class="text-right hidden sm:block">
                    <p
                        class="text-[10px] uppercase tracking-wider text-surface-400"
                    >
                        WhatsApp Number
                    </p>

                    <p class="text-sm font-medium text-surface-800 mt-1">
                        {{
                            whatsappNumber?.display_phone_number ||
                            whatsappNumber?.phone_number ||
                            "Not assigned"
                        }}
                    </p>
                </div>
            </div>

            <!-- Search -->

            <div>
                <div class="relative max-w-xl">
                    <input
                        v-model="search"
                        @keyup.enter="applySearch"
                        type="text"
                        placeholder="Search customer name or phone..."
                        class="w-full rounded-xl border border-surface-200 bg-white px-4 py-2.5 pr-24 text-sm focus:border-surface-400 focus:outline-none"
                    />

                    <button
                        type="button"
                        @click="applySearch"
                        class="absolute right-2 top-1.5 px-3 py-1.5 rounded-lg bg-slate-700 text-white text-xs"
                    >
                        Search
                    </button>
                </div>
            </div>

            <!-- Empty -->

            <div
                v-if="!customers.length"
                class="bg-white border border-surface-200 rounded-xl p-10 text-center"
            >
                <p class="text-sm font-medium text-surface-700">
                    No conversations found.
                </p>

                <p class="text-xs text-surface-400 mt-1">
                    Your assigned customer conversations will appear here.
                </p>
            </div>

            <!-- Conversations -->

            <div
                v-else
                class="bg-white border border-surface-200 rounded-xl overflow-y-auto h-[90vh]"
            >
                <Link
                    v-for="customer in customers"
                    :key="customer.id"
                    :href="route('executive.messages.show', customer.id)"
                    class="flex items-center gap-4 px-5 py-4 border-b border-surface-100 last:border-b-0 hover:bg-surface-50 transition"
                >
                    <!-- Avatar -->

                    <div
                        class="w-11 h-11 rounded-full bg-surface-100 flex items-center justify-center shrink-0 text-sm font-semibold text-surface-600"
                    >
                        {{ customer.name?.charAt(0)?.toUpperCase() || "?" }}
                    </div>

                    <!-- Main -->

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <p
                                class="text-sm text-surface-900 truncate"
                                :class="
                                    customer.unread_count
                                        ? 'font-bold'
                                        : 'font-semibold'
                                "
                            >
                                {{ customer.name }}
                            </p>

                            <span
                                v-if="customer.messages?.[0]?.created_at"
                                class="text-[10px] text-surface-400 shrink-0"
                            >
                                {{
                                    formatTime(customer.messages[0].created_at)
                                }}
                            </span>
                        </div>

                        <!--
                            IMPORTANT:
                            Never show customer.phone here.
                        -->

                        <p class="text-xs text-surface-400 mt-0.5">Customer</p>

                        <p
                            class="text-xs mt-1 truncate"
                            :class="
                                customer.unread_count
                                    ? 'text-surface-800 font-medium'
                                    : 'text-surface-500'
                            "
                        >
                            {{
                                customer.messages?.[0]?.body ||
                                "No messages yet"
                            }}
                        </p>
                    </div>

                    <!-- Unread -->

                    <div
                        v-if="Number(customer.unread_count) > 0"
                        class="min-w-5 h-5 px-1.5 rounded-full bg-emerald-600 text-white text-[10px] font-semibold flex items-center justify-center shrink-0"
                    >
                        {{
                            customer.unread_count > 99
                                ? "99+"
                                : customer.unread_count
                        }}
                    </div>
                </Link>
            </div>

            <!-- Pagination -->

            <div
                v-if="props.customers?.links?.length > 3"
                class="flex flex-wrap justify-center gap-1"
            >
                <template
                    v-for="(link, index) in props.customers.links"
                    :key="index"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="px-3 py-1.5 rounded-lg text-xs border"
                        :class="
                            link.active
                                ? 'bg-slate-600 text-white border-slate-600'
                                : 'bg-white text-surface-600 border-surface-200 hover:bg-surface-50'
                        "
                        v-html="link.label"
                    />

                    <span
                        v-else
                        class="px-3 py-1.5 text-xs text-surface-400"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </ExecutiveLayout>
</template>