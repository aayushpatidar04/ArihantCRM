<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { usePrivateChannel } from "@/Composables/useEcho";

import TeamAdminLayout from "@/Components/Layout/TeamAdminLayout.vue";

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

/*
|--------------------------------------------------------------------------
| Local customer list
|--------------------------------------------------------------------------
|
| We keep a local copy because realtime events need to update the inbox
| immediately without waiting for another HTTP request.
|
*/
const customerList = ref([...(props.customers?.data ?? [])]);

/*
|--------------------------------------------------------------------------
| Keep local list synchronized with Inertia props
|--------------------------------------------------------------------------
|
| Important for:
| - Search
| - Pagination
| - router.reload()
| - navigation back to the inbox
|
*/
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
| WhatsApp realtime channel
|--------------------------------------------------------------------------
|
| Customer sends a WhatsApp message:
|
| Meta
|   ↓
| Webhook
|   ↓
| Message created
|   ↓
| MessageReceived event
|   ↓
| Pusher private channel
|   ↓
| This inbox
|
*/
usePrivateChannel(`whatsapp.team.${teamId.value}`, {
    MessageReceived: (event) => {
        const message = event?.message;

        if (!message) {
            return;
        }

        /*
         * We only care about messages belonging to this inbox.
         */
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
            const existingCustomer = customerList.value[index];

            /*
             * New inbound message = unread.
             *
             * Outbound messages should NOT increase unread count.
             */
            const isInbound = message.direction === "inbound";

            const updatedCustomer = {
                ...existingCustomer,

                /*
                 * Update latest message preview.
                 */
                messages: [message],

                /*
                 * Increment unread only for inbound messages.
                 */
                unread_count:
                    Number(existingCustomer.unread_count ?? 0) +
                    (isInbound ? 1 : 0),
            };

            /*
             * Remove old position.
             */
            customerList.value.splice(index, 1);

            /*
             * Move conversation to the top.
             */
            customerList.value.unshift(updatedCustomer);

            return;
        }

        /*
             |--------------------------------------------------------------------------
             | Conversation is not currently present
             |--------------------------------------------------------------------------
             |
             | This can happen when:
             |
             | - customer is on another pagination page
             | - customer doesn't match the current search
             | - conversation was newly created
             |
             | In that case, let Laravel return the correct customer data,
             | including unread_count.
             |
             */

        router.reload({
            only: ["customers"],
            preserveScroll: true,
            preserveState: true,
        });
    },
});

const search = ref(props.filters?.search ?? "");

const applySearch = () => {
    router.get(
        route("team-admin.messages.index"),
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

    <TeamAdminLayout title="WhatsApp Inbox">
        <div class="h-full flex flex-col">
            <!-- Header -->
            <div class="mb-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-semibold text-surface-900">
                            WhatsApp Inbox
                        </h1>

                        <p class="text-sm text-surface-500 mt-1">
                            Conversations for
                            <strong>
                                {{ currentTeam?.name || "Current Team" }}
                            </strong>
                        </p>
                    </div>

                    <div class="hidden sm:block text-right">
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
            </div>

            <!-- Search -->
            <div class="mb-4">
                <div class="relative max-w-xl">
                    <input
                        v-model="search"
                        @keyup.enter="applySearch"
                        type="text"
                        placeholder="Search customer name or phone..."
                        class="w-full rounded-xl border border-surface-200 bg-white px-4 py-2.5 text-sm focus:border-surface-400 focus:outline-none"
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
                    Customers assigned to this workspace will appear here.
                </p>
            </div>

            <!-- Conversations -->
            <div
                v-else
                class="bg-white border border-surface-200 rounded-xl overflow-hidden"
            >
                <Link
                    v-for="customer in customers"
                    :key="customer.id"
                    :href="route('team-admin.messages.show', customer.id)"
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
                                class="text-sm font-semibold text-surface-900 truncate"
                                :class="
                                    customer.unread_count ? 'font-bold' : ''
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

                        <p class="text-xs text-surface-500 mt-0.5">
                            {{ customer.phone }}
                        </p>

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
                class="flex justify-center gap-1 mt-4"
            >
                <Link
                    v-for="link in props.customers.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    v-html="link.label"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs border',

                        link.active
                            ? 'bg-slate-700 text-white border-slate-700'
                            : 'bg-white text-surface-600 border-surface-200',

                        !link.url ? 'opacity-40 pointer-events-none' : '',
                    ]"
                />
            </div>
        </div>
    </TeamAdminLayout>
</template>
