<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
});

function formatDate(date) {
    if (!date) return "—";

    return new Intl.DateTimeFormat("en-IN", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(date));
}

function statusClass(status) {
    switch ((status ?? "").toUpperCase()) {
        case "APPROVED":
            return "bg-emerald-50 text-emerald-700";

        case "PENDING":
            return "bg-amber-50 text-amber-700";

        case "REJECTED":
            return "bg-red-50 text-red-700";

        default:
            return "bg-surface-100 text-surface-600";
    }
}

const headerComponent = computed(() => {
    return (props.template.components ?? []).find(
        (component) => (component.type ?? "").toUpperCase() === "HEADER",
    );
});

const bodyComponent = computed(() => {
    return (props.template.components ?? []).find(
        (component) => (component.type ?? "").toUpperCase() === "BODY",
    );
});

const footerComponent = computed(() => {
    return (props.template.components ?? []).find(
        (component) => (component.type ?? "").toUpperCase() === "FOOTER",
    );
});

const buttonsComponent = computed(() => {
    return (props.template.components ?? []).find(
        (component) => (component.type ?? "").toUpperCase() === "BUTTONS",
    );
});

const showSendModal = ref(false);
const sending = ref(false);

const recipient = ref("");
const headerMediaUrl = ref("");

const bodyVariables = ref([]);
const headerVariables = ref([]);

const bodyExampleValues = computed(() => {
    return bodyComponent.value?.example?.body_text?.[0] || [];
});

const bodyVariableCount = computed(() => {
    return bodyExampleValues.value.length;
});

const headerFormat = computed(() => {
    return headerComponent.value?.format || null;
});

function openSendModal() {
    recipient.value = "";
    headerMediaUrl.value = "";

    bodyVariables.value = Array.from(
        { length: bodyVariableCount.value },
        () => "",
    );

    headerVariables.value = [];

    showSendModal.value = true;
}

function closeSendModal() {
    if (sending.value) {
        return;
    }

    showSendModal.value = false;
}

function sendTestTemplate() {
    if (!recipient.value) {
        return;
    }

    sending.value = true;

    router.post(
        route("superadmin.whatsapp-templates.send-test", {
            whatsappNumber: props.template.whatsapp_number.id,

            whatsappTemplate: props.template.id,
        }),
        {
            to: recipient.value,

            body_variables: bodyVariables.value,

            header_variables: headerVariables.value,

            header_media_url: headerMediaUrl.value || null,
        },
        {
            preserveScroll: true,

            onSuccess: () => {
                showSendModal.value = false;
            },

            onFinish: () => {
                sending.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="template.name" />

    <SuperAdminLayout :title="template.name">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold text-surface-900">
                            {{ template.name }}
                        </h1>

                        <span
                            class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium"
                            :class="statusClass(template.status)"
                        >
                            {{ template.status || "Unknown" }}
                        </span>

                        <span
                            class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium"
                            :class="
                                template.is_enabled
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'bg-surface-100 text-surface-500'
                            "
                        >
                            {{ template.is_enabled ? "Enabled" : "Disabled" }}
                        </span>
                    </div>

                    <p class="mt-1 text-sm text-surface-500">
                        Meta-approved WhatsApp template configuration.
                    </p>
                </div>

                <div class="flex gap-2">
                    <Link
                        :href="route('superadmin.whatsapp-templates.index')"
                        class="px-4 py-2 text-sm border border-surface-200 rounded-lg hover:bg-surface-50"
                    >
                        ← Back
                    </Link>

                    <Link
                        :href="
                            route(
                                'superadmin.whatsapp-templates.edit',
                                template.id,
                            )
                        "
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-700"
                    >
                        Configure
                    </Link>

                    <button
                        v-if="template.status === 'APPROVED'"
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-white bg-amber-500 rounded-lg hover:bg-amber-600"
                        @click="openSendModal"
                    >
                        Send Test Template
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Template preview -->
                <div
                    class="lg:col-span-2 bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Template Preview
                        </h2>
                    </div>

                    <div class="p-6">
                        <!-- Header -->
                        <div v-if="headerComponent" class="mb-4">
                            <p
                                class="text-xs font-medium text-surface-500 mb-2"
                            >
                                HEADER
                            </p>

                            <div
                                v-if="
                                    headerComponent.format === 'IMAGE' &&
                                    template.local_config?.header_media_url
                                "
                                class="rounded-lg overflow-hidden border border-surface-200 mb-3"
                            >
                                <img
                                    :src="
                                        template.local_config.header_media_url
                                    "
                                    class="max-h-80 w-full object-contain bg-surface-50"
                                />
                            </div>

                            <div
                                v-if="headerComponent.text"
                                class="text-sm font-medium text-surface-900"
                            >
                                {{ headerComponent.text }}
                            </div>

                            <div
                                v-if="
                                    headerComponent.format &&
                                    !headerComponent.text
                                "
                                class="text-xs text-surface-500"
                            >
                                {{ headerComponent.format }} header
                            </div>
                        </div>

                        <!-- Body -->
                        <div v-if="bodyComponent" class="mb-4">
                            <p
                                class="text-xs font-medium text-surface-500 mb-2"
                            >
                                BODY
                            </p>

                            <div
                                class="whitespace-pre-wrap text-sm text-surface-800 leading-6"
                            >
                                {{ bodyComponent.text }}
                            </div>
                        </div>

                        <!-- Footer -->
                        <div
                            v-if="footerComponent?.text"
                            class="pt-3 border-t border-surface-100"
                        >
                            <p class="text-xs text-surface-500">
                                {{ footerComponent.text }}
                            </p>
                        </div>

                        <!-- Buttons -->
                        <div
                            v-if="buttonsComponent?.buttons?.length"
                            class="mt-4 space-y-2"
                        >
                            <p class="text-xs font-medium text-surface-500">
                                BUTTONS
                            </p>

                            <div
                                v-for="(button, index) in buttonsComponent
                                    .buttons"
                                :key="index"
                                class="border border-surface-200 rounded-lg px-4 py-2 text-sm text-slate-600"
                            >
                                {{ button.text }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Information -->
                <div class="space-y-6">
                    <!-- Number -->
                    <div
                        class="bg-white border border-surface-200 rounded-xl shadow-sm"
                    >
                        <div class="px-6 py-4 border-b border-surface-200">
                            <h2 class="text-sm font-semibold text-surface-900">
                                WhatsApp Number
                            </h2>
                        </div>

                        <div class="p-6">
                            <p class="text-sm font-medium text-surface-900">
                                {{
                                    template.whatsapp_number
                                        ?.display_phone_number ||
                                    template.whatsapp_number?.phone_number ||
                                    "—"
                                }}
                            </p>

                            <p
                                v-if="template.whatsapp_number?.verified_name"
                                class="text-xs text-surface-500 mt-1"
                            >
                                {{ template.whatsapp_number.verified_name }}
                            </p>

                            <Link
                                v-if="template.whatsapp_number"
                                :href="
                                    route(
                                        'superadmin.whatsapp-numbers.show',
                                        template.whatsapp_number.id,
                                    )
                                "
                                class="inline-block mt-3 text-xs font-medium text-slate-600 hover:text-slate-700"
                            >
                                View WhatsApp Number →
                            </Link>
                        </div>
                    </div>

                    <!-- Meta details -->
                    <div
                        class="bg-white border border-surface-200 rounded-xl shadow-sm"
                    >
                        <div class="px-6 py-4 border-b border-surface-200">
                            <h2 class="text-sm font-semibold text-surface-900">
                                Meta Information
                            </h2>
                        </div>

                        <div class="p-6 space-y-4">
                            <div>
                                <p class="text-xs text-surface-500">
                                    Template ID
                                </p>

                                <p
                                    class="text-sm font-medium text-surface-900 break-all"
                                >
                                    {{ template.template_id || "—" }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-surface-500">Language</p>

                                <p class="text-sm font-medium text-surface-900">
                                    {{ template.language }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-surface-500">Category</p>

                                <p class="text-sm font-medium text-surface-900">
                                    {{ template.category || "—" }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-surface-500">
                                    Last Synced
                                </p>

                                <p class="text-sm font-medium text-surface-900">
                                    {{ formatDate(template.last_synced_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Local configuration -->
            <div
                class="mt-6 bg-white border border-surface-200 rounded-xl shadow-sm"
            >
                <div class="px-6 py-4 border-b border-surface-200">
                    <h2 class="text-sm font-semibold text-surface-900">
                        Local Configuration
                    </h2>
                </div>

                <div class="p-6">
                    <div
                        v-if="template.local_config?.header_media_url"
                        class="mb-5"
                    >
                        <p class="text-xs text-surface-500 mb-1">
                            Header Media URL
                        </p>

                        <p class="text-sm text-surface-700 break-all">
                            {{ template.local_config.header_media_url }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-2">
                            Variable Mappings
                        </p>

                        <pre
                            class="bg-surface-50 border border-surface-200 rounded-lg p-4 text-xs overflow-x-auto"
                            >{{
                                JSON.stringify(
                                    template.local_config?.variables || {},
                                    null,
                                    2,
                                )
                            }}</pre
                        >
                    </div>
                </div>
            </div>

            <div
                v-if="showSendModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
            >
                <div class="w-full max-w-lg bg-white rounded-xl shadow-xl">
                    <!-- Modal Header -->
                    <div
                        class="px-6 py-4 border-b border-surface-200 flex items-center justify-between"
                    >
                        <div>
                            <h3
                                class="text-base font-semibold text-surface-900"
                            >
                                Send Test Template
                            </h3>

                            <p class="text-xs text-surface-500 mt-1">
                                {{ template.name }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="text-surface-400 hover:text-surface-700"
                            @click="closeSendModal"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-5">
                        <!-- Recipient -->
                        <div>
                            <label
                                class="block text-xs font-medium text-surface-700 mb-1"
                            >
                                Recipient WhatsApp Number
                            </label>

                            <input
                                v-model="recipient"
                                type="text"
                                placeholder="919876543210"
                                class="w-full rounded-lg border border-surface-300 px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-500"
                            />

                            <p class="mt-1 text-xs text-surface-500">
                                Enter the number in international format without
                                + or spaces.
                            </p>
                        </div>

                        <!-- Header Media -->
                        <div v-if="headerFormat === 'IMAGE'">
                            <label
                                class="block text-xs font-medium text-surface-700 mb-1"
                            >
                                Header Image URL
                            </label>

                            <input
                                v-model="headerMediaUrl"
                                type="url"
                                placeholder="https://example.com/image.jpg"
                                class="w-full rounded-lg border border-surface-300 px-3 py-2 text-sm"
                            />

                            <p class="mt-1 text-xs text-surface-500">
                                Optional. Use this to override the template's
                                image header.
                            </p>
                        </div>

                        <!-- Body Variables -->
                        <div v-if="bodyVariables.length">
                            <label
                                class="block text-xs font-medium text-surface-700 mb-2"
                            >
                                Body Variables
                            </label>

                            <div
                                v-for="(variable, index) in bodyVariables"
                                :key="index"
                                class="mb-3"
                            >
                                <label
                                    class="block text-xs text-surface-500 mb-1"
                                >
                                    Variable
                                    {{ index + 1 }}
                                </label>

                                <input
                                    v-model="bodyVariables[index]"
                                    type="text"
                                    :placeholder="`Value for {{${index + 1}}}`"
                                    class="w-full rounded-lg border border-surface-300 px-3 py-2 text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div
                        class="px-6 py-4 border-t border-surface-200 flex justify-end gap-2"
                    >
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-surface-700 bg-white border border-surface-200 rounded-lg hover:bg-surface-50"
                            @click="closeSendModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            :disabled="sending || !recipient"
                            class="px-4 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="sendTestTemplate"
                        >
                            {{ sending ? "Sending..." : "Send Template" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
