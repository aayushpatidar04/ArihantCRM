<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";

import { Head, Link, router, usePage } from "@inertiajs/vue3";
import axios from "axios";

import {
    ArrowDown,
    ArrowLeft,
    Calendar,
    Check,
    CheckCheck,
    Clock,
    FileText,
    Paperclip,
    Search,
    Send,
    User,
    X,
    LoaderCircle,
} from "lucide-vue-next";

import { usePrivateChannel } from "@/Composables/useEcho";
import ExecutiveLayout from "@/Components/Layout/ExecutiveLayout.vue";
import { useToast } from "@/Composables/useToast";

const page = usePage();
const { success, error } = useToast();

const responseErrorMessage = (requestError, fallback) => {
    const responseData = requestError?.response?.data;
    const validationError = Object.values(responseData?.errors ?? {})
        .flat()
        .find(Boolean);

    return (
        responseData?.message ||
        responseData?.failure_reason ||
        validationError ||
        (typeof responseData === "string" ? responseData : null) ||
        requestError?.message ||
        fallback
    );
};

const showWindowClosedError = () => {
    error("The 24-hour WhatsApp window is closed. Please use a template.");
};

const currentTeam = computed(
    () => page.props.workspace?.current_team ?? props.customer?.team ?? null,
);

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },

    messages: {
        type: Array,
        default: () => [],
    },

    templates: {
        type: Array,
        default: () => [],
    },

    conversation: {
        type: Object,
        default: () => ({
            window_open: false,
            window_expires_at: null,
            last_inbound_at: null,
        }),
    },

    messagePagination: {
        type: Object,
        default: () => ({
            has_more: false,
            next_cursor: null,
        }),
    },
});

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

const messageList = ref(
    [...props.messages].sort(
        (a, b) =>
            new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
    ),
);

const messagesContainer = ref(null);

const hasMoreMessages = ref(Boolean(props.messagePagination?.has_more));

const nextCursor = ref(props.messagePagination?.next_cursor ?? null);

const loadingOlderMessages = ref(false);

const loadingAroundDate = ref(false);

const isNearBottom = ref(true);

const showJumpToLatest = ref(false);

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

const searchText = ref("");

const activeSearch = ref("");

const searchLoading = ref(false);

const searchResults = ref([]);

let searchTimer = null;

/*
|--------------------------------------------------------------------------
| Jump to date
|--------------------------------------------------------------------------
*/

const jumpDate = ref("");

const showDatePicker = ref(false);

/*
|--------------------------------------------------------------------------
| Composer
|--------------------------------------------------------------------------
*/

const messageText = ref("");

const selectedFile = ref(null);

const composerMode = ref(
    props.conversation.window_open ? "normal" : "template",
);

/*
|--------------------------------------------------------------------------
| Template Preview
|--------------------------------------------------------------------------
*/

const selectedTemplate = ref(null);

const templatePreviewOpen = ref(false);

const templateBodyVariables = ref([]);

const templateHeaderVariables = ref([]);

const templateHeaderMediaUrl = ref("");

const templateButtonVariables = ref([]);

const sending = ref(false);

/*
|--------------------------------------------------------------------------
| Conversation Window
|--------------------------------------------------------------------------
*/

const currentTime = ref(Date.now());

let timer = null;

const windowOpen = computed(() => {
    if (!props.conversation.window_expires_at) {
        return false;
    }

    return (
        currentTime.value <
        new Date(props.conversation.window_expires_at).getTime()
    );
});

const windowExpiresAt = computed(() => {
    if (!props.conversation.window_expires_at) {
        return null;
    }

    return new Date(props.conversation.window_expires_at);
});

const remainingTime = computed(() => {
    if (!windowExpiresAt.value || !windowOpen.value) {
        return null;
    }

    const difference = windowExpiresAt.value.getTime() - currentTime.value;

    if (difference <= 0) {
        return null;
    }

    const totalMinutes = Math.floor(difference / 1000 / 60);

    const hours = Math.floor(totalMinutes / 60);

    const minutes = totalMinutes % 60;

    if (hours > 0) {
        return `${hours}h ${minutes}m remaining`;
    }

    return `${minutes}m remaining`;
});

/*
|--------------------------------------------------------------------------
| Customer
|--------------------------------------------------------------------------
*/

/*
 * IMPORTANT:
 * Executive must never see the real customer phone number.
 *
 * Backend should preferably already return a masked value.
 * This is only an additional frontend safety layer.
 */
const customerPhone = computed(() => {
    const phone = props.customer?.masked_phone ?? props.customer?.phone ?? null;

    if (!phone) {
        return "No phone number";
    }

    const digits = String(phone).replace(/\D/g, "");

    if (digits.length <= 4) {
        return "*".repeat(digits.length);
    }

    return "*".repeat(digits.length - 4) + digits.slice(-4);
});

const customerTeam = computed(() => {
    return props.customer?.team?.name || "—";
});

const activeConversationWhatsappNumberId = computed(() => {
    const fromQuery = new URLSearchParams(window.location.search).get(
        "whatsapp_number_id",
    );
    z;
    if (fromQuery) {
        return Number(fromQuery) || null;
    }

    return Number(props.customer?.team?.whatsapp_number_id ?? 0) || null;
});

const whatsappNumber = computed(() => {
    return (
        props.customer?.team?.whatsapp_number?.display_phone_number ||
        props.customer?.team?.whatsapp_number?.phone_number ||
        null
    );
});

const canSendNormalMessage = computed(() => {
    return windowOpen.value;
});

/*
|--------------------------------------------------------------------------
| Composer switching
|--------------------------------------------------------------------------
*/

const openTemplateComposer = () => {
    composerMode.value = "template";

    selectedTemplate.value = null;

    closeTemplatePreview();

    resetTemplatePreviewState();
};

const openNormalComposer = () => {
    if (!windowOpen.value) {
        return;
    }

    composerMode.value = "normal";

    selectedTemplate.value = null;

    closeTemplatePreview();

    resetTemplatePreviewState();
};

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const normalizeMessage = (message) => {
    return {
        ...message,
        id: Number(message.id),
    };
};

const messageExists = (messageId) => {
    return messageList.value.some(
        (item) => Number(item.id) === Number(messageId),
    );
};

const sortMessages = () => {
    messageList.value.sort(
        (a, b) =>
            new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
    );
};

const formatTime = (date) => {
    if (!date) {
        return "";
    }

    return new Date(date).toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    });
};

const dateKey = (date) => {
    if (!date) {
        return "";
    }

    const value = new Date(date);

    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(
        2,
        "0",
    )}-${String(value.getDate()).padStart(2, "0")}`;
};

const isSameDate = (first, second) => {
    return dateKey(first) === dateKey(second);
};

const formatFullDate = (date) => {
    if (!date) {
        return "";
    }

    return new Date(date).toLocaleDateString([], {
        weekday: "short",
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
};

const shouldShowDateSeparator = (index) => {
    if (index === 0) {
        return true;
    }

    const current = messageList.value[index];

    const previous = messageList.value[index - 1];

    return !isSameDate(current.created_at, previous.created_at);
};

const messageStatusIcon = (message) => {
    if (message.direction !== "outbound") {
        return null;
    }

    if (message.status === "read") {
        return "read";
    }

    if (message.status === "delivered" || message.status === "sent") {
        return "delivered";
    }

    if (message.status === "pending" || message.status === "queued") {
        return "pending";
    }

    return "failed";
};

/*
|--------------------------------------------------------------------------
| Scroll
|--------------------------------------------------------------------------
*/

const scrollToBottom = (behavior = "smooth") => {
    if (!messagesContainer.value) {
        return;
    }

    messagesContainer.value.scrollTo({
        top: messagesContainer.value.scrollHeight,
        behavior,
    });

    isNearBottom.value = true;

    showJumpToLatest.value = false;
};

const handleScroll = async () => {
    if (!messagesContainer.value) {
        return;
    }

    const element = messagesContainer.value;

    const nearBottom =
        element.scrollHeight - element.scrollTop - element.clientHeight < 120;

    isNearBottom.value = nearBottom;

    showJumpToLatest.value = !nearBottom && messageList.value.length > 0;

    if (
        element.scrollTop <= 100 &&
        hasMoreMessages.value &&
        !loadingOlderMessages.value &&
        !loadingAroundDate.value &&
        !activeSearch.value
    ) {
        await loadOlderMessages();
    }
};

/*
|--------------------------------------------------------------------------
| Older messages
|--------------------------------------------------------------------------
*/

const loadOlderMessages = async () => {
    if (
        loadingOlderMessages.value ||
        !hasMoreMessages.value ||
        !nextCursor.value
    ) {
        return;
    }

    if (!messagesContainer.value) {
        return;
    }

    loadingOlderMessages.value = true;

    const container = messagesContainer.value;

    const previousScrollHeight = container.scrollHeight;

    const previousScrollTop = container.scrollTop;

    try {
        const response = await axios.get(
            route("executive.messages.history", {
                customer: props.customer.id,
            }),
            {
                params: {
                    before_id: nextCursor.value,
                    limit: 30,
                },
            },
        );

        const data = response.data;

        const olderMessages = (data.messages || []).map(normalizeMessage);

        if (olderMessages.length) {
            const existingIds = new Set(
                messageList.value.map((message) => Number(message.id)),
            );

            const newMessages = olderMessages.filter(
                (message) => !existingIds.has(Number(message.id)),
            );

            messageList.value = [...newMessages, ...messageList.value];

            sortMessages();

            await nextTick();

            const newScrollHeight = container.scrollHeight;

            container.scrollTop =
                previousScrollTop + (newScrollHeight - previousScrollHeight);
        }

        hasMoreMessages.value = Boolean(data.has_more);

        nextCursor.value = data.next_cursor ?? null;
    } catch (error) {
        console.error("Unable to load older messages:", error);
    } finally {
        loadingOlderMessages.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

const clearSearch = () => {
    searchText.value = "";

    activeSearch.value = "";

    searchResults.value = [];

    router.reload({
        only: ["messages", "messagePagination"],

        preserveScroll: true,

        preserveState: true,

        onSuccess: () => {
            messageList.value = [...page.props.messages].map(normalizeMessage);

            sortMessages();

            hasMoreMessages.value = Boolean(
                page.props.messagePagination?.has_more,
            );

            nextCursor.value =
                page.props.messagePagination?.next_cursor ?? null;

            nextTick(() => {
                scrollToBottom("auto");
            });
        },
    });
};

const performSearch = async () => {
    const query = searchText.value.trim();

    if (!query) {
        clearSearch();

        return;
    }

    searchLoading.value = true;

    activeSearch.value = query;

    try {
        const response = await axios.get(
            route("executive.messages.history", {
                customer: props.customer.id,
            }),
            {
                params: {
                    search: query,
                    limit: 100,
                },
            },
        );

        const data = response.data;

        searchResults.value = (data.messages || []).map(normalizeMessage);

        messageList.value = [...searchResults.value];

        sortMessages();
    } catch (error) {
        console.error("Unable to search messages:", error);
    } finally {
        searchLoading.value = false;
    }
};

const scheduleSearch = () => {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
        performSearch();
    }, 400);
};

const highlightSearchText = (text) => {
    if (!text || !activeSearch.value) {
        return text || "";
    }

    const escaped = activeSearch.value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

    const regex = new RegExp(`(${escaped})`, "gi");

    return text.replace(
        regex,
        "<mark class='bg-yellow-200 text-surface-900 rounded px-0.5'>$1</mark>",
    );
};

/*
|--------------------------------------------------------------------------
| Jump to date
|--------------------------------------------------------------------------
*/

const jumpToDate = async () => {
    if (!jumpDate.value) {
        return;
    }

    loadingAroundDate.value = true;

    try {
        const response = await axios.get(
            route("executive.messages.history", {
                customer: props.customer.id,
            }),
            {
                params: {
                    date: jumpDate.value,
                    limit: 30,
                },
            },
        );

        const data = response.data;

        messageList.value = (data.messages || []).map(normalizeMessage);

        sortMessages();

        hasMoreMessages.value = Boolean(data.has_more);

        nextCursor.value = data.next_cursor ?? null;

        activeSearch.value = "";

        searchText.value = "";

        await nextTick();

        const targetIndex = messageList.value.findIndex(
            (message) => dateKey(message.created_at) === jumpDate.value,
        );

        if (targetIndex !== -1 && messagesContainer.value) {
            const targetElement = messagesContainer.value.querySelector(
                `[data-message-id="${messageList.value[targetIndex].id}"]`,
            );

            targetElement?.scrollIntoView({
                behavior: "smooth",
                block: "center",
            });
        }
    } catch (error) {
        console.error("Unable to jump to date:", error);
    } finally {
        loadingAroundDate.value = false;

        showDatePicker.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Template helpers
|--------------------------------------------------------------------------
*/

const parseMaybeJson = (value, fallback = {}) => {
    if (!value) {
        return fallback;
    }

    if (typeof value === "object") {
        return value;
    }

    if (typeof value !== "string") {
        return fallback;
    }

    try {
        const parsed = JSON.parse(value);

        return parsed ?? fallback;
    } catch {
        return fallback;
    }
};

const selectedTemplateObject = computed(() => {
    if (!selectedTemplate.value) {
        return null;
    }

    return (
        props.templates.find(
            (template) =>
                Number(template.id) === Number(selectedTemplate.value),
        ) || null
    );
});

const normalizedTemplateComponents = computed(() => {
    const components = parseMaybeJson(
        selectedTemplateObject.value?.components,
        [],
    );

    return Array.isArray(components) ? components : [];
});

const templateLocalConfig = computed(() => {
    const config = parseMaybeJson(
        selectedTemplateObject.value?.local_config,
        {},
    );

    return config && typeof config === "object" && !Array.isArray(config)
        ? config
        : {};
});

const getTemplateComponent = (type) => {
    return (
        normalizedTemplateComponents.value.find(
            (component) =>
                String(component?.type || "").toUpperCase() ===
                String(type).toUpperCase(),
        ) || null
    );
};

const bodyComponent = computed(() => getTemplateComponent("BODY"));

const headerComponent = computed(() => getTemplateComponent("HEADER"));

const buttonsComponent = computed(() => getTemplateComponent("BUTTONS"));

const getVariableNumbers = (text) => {
    if (!text) {
        return [];
    }

    const matches = String(text).match(/\{\{\s*(\d+)\s*\}\}/g);

    if (!matches) {
        return [];
    }

    return [
        ...new Set(
            matches
                .map((match) => {
                    const number = match.match(/\d+/);

                    return number ? Number(number[0]) : null;
                })
                .filter(Boolean),
        ),
    ].sort((a, b) => a - b);
};

const getVariableCount = (text) => {
    const numbers = getVariableNumbers(text);

    return numbers.length ? Math.max(...numbers) : 0;
};

const getDefaultBodyVariables = (count) => {
    const configured = Array.isArray(templateLocalConfig.value?.variables)
        ? templateLocalConfig.value.variables
        : [];

    return Array.from({ length: count }, (_, index) => configured[index] ?? "");
};

const getDefaultHeaderVariables = (count) => {
    const configured = Array.isArray(
        templateLocalConfig.value?.header_variables,
    )
        ? templateLocalConfig.value.header_variables
        : [];

    return Array.from({ length: count }, (_, index) => configured[index] ?? "");
};

const getDefaultHeaderMediaUrl = () => {
    return (
        templateLocalConfig.value?.header_media_url ||
        headerComponent.value?.example?.header_handle?.[0] ||
        ""
    );
};

const getButtonVariableDefinitions = () => {
    const buttons = buttonsComponent.value?.buttons;

    if (!Array.isArray(buttons)) {
        return [];
    }

    const definitions = [];

    buttons.forEach((button, buttonIndex) => {
        const type = String(button?.type || "").toUpperCase();

        if (type !== "URL" || !button?.url) {
            return;
        }

        const variables = getVariableNumbers(button.url);

        variables.forEach((variable) => {
            definitions.push({
                buttonIndex,
                buttonText: button.text || `Button ${buttonIndex + 1}`,
                variable,
                label: `${
                    button.text || `Button ${buttonIndex + 1}`
                } URL variable ${variable}`,
                value: "",
            });
        });
    });

    const configured = Array.isArray(
        templateLocalConfig.value?.button_variables,
    )
        ? templateLocalConfig.value.button_variables
        : [];

    return definitions.map((item, index) => ({
        ...item,
        value: configured[index] ?? "",
    }));
};

const templateHeaderFormat = computed(() => {
    return String(headerComponent.value?.format || "").toUpperCase();
});

const hasTemplateBodyVariables = computed(
    () => templateBodyVariables.value.length > 0,
);

const hasTemplateHeaderVariables = computed(
    () => templateHeaderVariables.value.length > 0,
);

const hasTemplateHeaderMedia = computed(() =>
    ["IMAGE", "VIDEO", "DOCUMENT"].includes(templateHeaderFormat.value),
);

const hasTemplateButtonVariables = computed(
    () => templateButtonVariables.value.length > 0,
);

const replaceTemplateVariables = (text, variables = []) => {
    if (!text) {
        return "";
    }

    return String(text).replace(/\{\{\s*(\d+)\s*\}\}/g, (match, number) => {
        const index = Number(number) - 1;

        const value = variables[index];

        if (
            value !== undefined &&
            value !== null &&
            String(value).trim() !== ""
        ) {
            return String(value);
        }

        return match;
    });
};

const templatePreviewHeader = computed(() => {
    const header = headerComponent.value;

    if (!header) {
        return null;
    }

    const format = String(header.format || "TEXT").toUpperCase();

    if (format === "TEXT") {
        return {
            type: "text",
            value: replaceTemplateVariables(
                header.text || "",
                templateHeaderVariables.value,
            ),
        };
    }

    if (["IMAGE", "VIDEO", "DOCUMENT"].includes(format)) {
        return {
            type: format.toLowerCase(),
            value: templateHeaderMediaUrl.value,
        };
    }

    return null;
});

const templatePreviewBody = computed(() => {
    if (!bodyComponent.value) {
        return "";
    }

    return replaceTemplateVariables(
        bodyComponent.value.text || "",
        templateBodyVariables.value,
    );
});

const templatePreviewButtons = computed(() => {
    const buttons = buttonsComponent.value?.buttons;

    if (!Array.isArray(buttons)) {
        return [];
    }

    return buttons.map((button, buttonIndex) => {
        const type = String(button?.type || "").toUpperCase();

        let previewUrl = button?.url || "";

        if (type === "URL") {
            const variables = templateButtonVariables.value
                .filter((item) => item.buttonIndex === buttonIndex)
                .sort((a, b) => a.variable - b.variable)
                .map((item) => item.value);

            previewUrl = replaceTemplateVariables(previewUrl, variables);
        }

        return {
            ...button,
            type,
            previewUrl,
        };
    });
});

const templateHasMissingVariables = computed(() => {
    const bodyMissing = templateBodyVariables.value.some(
        (value) => !String(value ?? "").trim(),
    );

    const headerMissing = templateHeaderVariables.value.some(
        (value) => !String(value ?? "").trim(),
    );

    const mediaMissing =
        hasTemplateHeaderMedia.value &&
        !String(templateHeaderMediaUrl.value ?? "").trim();

    const buttonMissing = templateButtonVariables.value.some(
        (item) => !String(item.value ?? "").trim(),
    );

    return bodyMissing || headerMissing || mediaMissing || buttonMissing;
});

const resetTemplatePreviewState = () => {
    templateBodyVariables.value = [];

    templateHeaderVariables.value = [];

    templateHeaderMediaUrl.value = "";

    templateButtonVariables.value = [];
};

const initializeTemplatePreview = () => {
    if (!selectedTemplateObject.value) {
        resetTemplatePreviewState();

        return;
    }

    templateBodyVariables.value = getDefaultBodyVariables(
        getVariableCount(bodyComponent.value?.text || ""),
    );

    templateHeaderVariables.value = getDefaultHeaderVariables(
        String(headerComponent.value?.format || "").toUpperCase() === "TEXT"
            ? getVariableCount(headerComponent.value?.text || "")
            : 0,
    );

    templateHeaderMediaUrl.value = getDefaultHeaderMediaUrl();

    templateButtonVariables.value = getButtonVariableDefinitions();
};

const openTemplatePreview = () => {
    if (!selectedTemplateObject.value) {
        return;
    }

    initializeTemplatePreview();

    templatePreviewOpen.value = true;
};

const closeTemplatePreview = () => {
    if (sending.value) {
        return;
    }

    templatePreviewOpen.value = false;
};

/*
|--------------------------------------------------------------------------
| Build template components
|--------------------------------------------------------------------------
*/

const buildTemplateComponents = () => {
    const components = [];

    if (templateBodyVariables.value.length) {
        components.push({
            type: "body",

            parameters: templateBodyVariables.value.map((value) => ({
                type: "text",
                text: String(value),
            })),
        });
    }

    if (headerComponent.value) {
        const format = String(headerComponent.value.format || "").toUpperCase();

        if (format === "TEXT" && templateHeaderVariables.value.length) {
            components.push({
                type: "header",

                parameters: templateHeaderVariables.value.map((value) => ({
                    type: "text",
                    text: String(value),
                })),
            });
        }

        if (
            ["IMAGE", "VIDEO", "DOCUMENT"].includes(format) &&
            templateHeaderMediaUrl.value
        ) {
            const mediaType = format.toLowerCase();

            components.push({
                type: "header",

                parameters: [
                    {
                        type: mediaType,

                        [mediaType]: {
                            link: templateHeaderMediaUrl.value,
                        },
                    },
                ],
            });
        }
    }

    const buttonVariablesByButton = {};

    templateButtonVariables.value.forEach((item) => {
        if (!buttonVariablesByButton[item.buttonIndex]) {
            buttonVariablesByButton[item.buttonIndex] = [];
        }

        buttonVariablesByButton[item.buttonIndex][item.variable - 1] = String(
            item.value,
        );
    });

    Object.entries(buttonVariablesByButton).forEach(
        ([buttonIndex, variables]) => {
            components.push({
                type: "button",
                sub_type: "url",
                index: String(buttonIndex),

                parameters: variables.map((value) => ({
                    type: "text",
                    text: value,
                })),
            });
        },
    );

    return components;
};

/*
|--------------------------------------------------------------------------
| Sending
|--------------------------------------------------------------------------
*/

const selectFile = (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    selectedFile.value = file;
};

const removeFile = () => {
    selectedFile.value = null;
};

const sendCurrentMessage = () => {
    if (sending.value) {
        return;
    }

    if (selectedFile.value) {
        sendAttachment();

        return;
    }

    sendTextMessage();
};

const appendOwnMessage = async (message) => {
    if (!message) {
        return;
    }

    if (!messageExists(message.id)) {
        messageList.value.push(normalizeMessage(message));

        sortMessages();
    }

    await nextTick();

    scrollToBottom();
};

const sendTextMessage = async () => {
    if (!messageText.value.trim()) {
        return;
    }

    if (!canSendNormalMessage.value) {
        showWindowClosedError();
        return;
    }

    if (sending.value) {
        return;
    }

    sending.value = true;

    try {
        const response = await axios.post(
            route("executive.messages.send", props.customer.id),
            {
                type: "text",
                body: messageText.value.trim(),
            },
        );

        await appendOwnMessage(response.data.message);

        messageText.value = "";
        success("Message sent.");
    } catch (requestError) {
        console.error("Unable to send message:", requestError);
        error(responseErrorMessage(requestError, "Unable to send message."));
    } finally {
        sending.value = false;
    }
};

const detectFileType = (file) => {
    if (file.type.startsWith("image/")) {
        return "image";
    }

    if (file.type.startsWith("audio/")) {
        return "audio";
    }

    if (file.type.startsWith("video/")) {
        return "video";
    }

    return "document";
};

const sendAttachment = async () => {
    if (!selectedFile.value) {
        return;
    }

    if (!canSendNormalMessage.value) {
        showWindowClosedError();
        return;
    }

    const form = new FormData();

    form.append("type", detectFileType(selectedFile.value));

    form.append("media", selectedFile.value);

    if (messageText.value?.trim()) {
        form.append("caption", messageText.value.trim());
    }

    sending.value = true;

    try {
        const response = await axios.post(
            route("executive.messages.send-media", props.customer.id),
            form,
            {
                headers: {
                    "Content-Type": "multipart/form-data",

                    "X-Requested-With": "XMLHttpRequest",
                },
            },
        );

        await appendOwnMessage(response.data.message);

        selectedFile.value = null;

        messageText.value = "";
        success("Media sent.");
    } catch (requestError) {
        console.error("Unable to send attachment:", requestError);
        error(responseErrorMessage(requestError, "Unable to send media."));
    } finally {
        sending.value = false;
    }
};

const sendTemplate = async () => {
    if (!selectedTemplateObject.value) {
        return;
    }

    if (templateHasMissingVariables.value) {
        error("Please fill in all required template variables.");
        return;
    }

    if (sending.value) {
        return;
    }

    sending.value = true;

    try {
        const response = await axios.post(
            route("executive.messages.send-template", props.customer.id),
            {
                template_id: selectedTemplateObject.value.id,

                components: buildTemplateComponents(),
            },
            {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            },
        );

        await appendOwnMessage(response.data.message);

        selectedTemplate.value = null;

        templatePreviewOpen.value = false;

        resetTemplatePreviewState();

        if (windowOpen.value) {
            composerMode.value = "normal";
        }
        success("Template sent.");
    } catch (requestError) {
        console.error("Unable to send template:", requestError);
        error(responseErrorMessage(requestError, "Unable to send template."));
    } finally {
        sending.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Mark read
|--------------------------------------------------------------------------
*/

const markConversationRead = () => {
    router.post(
        route("executive.messages.mark-read", props.customer.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

/*
|--------------------------------------------------------------------------
| Real-time broadcasting
|--------------------------------------------------------------------------
|
| Executive can only access customers assigned to him
| or previously owned by him.
|
| The team channel is used because your current
| MessageCreated/NewInboundMessage events broadcast
| on whatsapp.team.{team_id}.
|
*/

usePrivateChannel(
    `whatsapp.team.${currentTeam.value?.id ?? props.customer.team_id}`,
    {
        "message.created": (event) => {
            const message = event.message;

            if (!message) {
                return;
            }

            if (Number(message.sent_by) === Number(page.props.auth?.user?.id)) {
                return;
            }

            if (Number(message.customer_id) !== Number(props.customer.id)) {
                return;
            }

            if (messageExists(message.id)) {
                return;
            }

            messageList.value.push(normalizeMessage(message));

            sortMessages();

            if (isNearBottom.value) {
                nextTick(() => {
                    scrollToBottom();
                });
            } else {
                showJumpToLatest.value = true;
            }
        },

        "message.received": (event) => {
            const message = event.message;

            if (!message) {
                return;
            }

            if (Number(message.customer_id) !== Number(props.customer.id)) {
                return;
            }

            if (messageExists(message.id)) {
                return;
            }

            if (activeSearch.value || loadingAroundDate.value) {
                return;
            }

            messageList.value.push(normalizeMessage(message));

            sortMessages();

            if (message.direction === "inbound") {
                markConversationRead();
            }

            if (isNearBottom.value) {
                nextTick(() => {
                    scrollToBottom();
                });
            } else {
                showJumpToLatest.value = true;
            }
        },

        "message.status.updated": (event) => {
            const updatedMessage = event.message;

            if (!updatedMessage) {
                return;
            }

            if (
                Number(updatedMessage.customer_id) !== Number(props.customer.id)
            ) {
                return;
            }

            const index = messageList.value.findIndex(
                (item) => Number(item.id) === Number(updatedMessage.id),
            );

            if (index === -1) {
                return;
            }

            messageList.value[index] = normalizeMessage(updatedMessage);

            if (updatedMessage.status === "failed") {
                error(
                    updatedMessage.failure_reason ||
                        "WhatsApp delivery failed.",
                );
            }
        },
    },
);

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(() => {
    timer = setInterval(() => {
        currentTime.value = Date.now();
    }, 30_000);

    if (messagesContainer.value) {
        messagesContainer.value.addEventListener("scroll", handleScroll, {
            passive: true,
        });
    }

    nextTick(() => {
        setTimeout(() => {
            scrollToBottom("auto");
        }, 50);
    });

    markConversationRead();
});

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
    }

    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    if (messagesContainer.value) {
        messagesContainer.value.removeEventListener("scroll", handleScroll);
    }
});

watch(windowOpen, (value) => {
    if (value && composerMode.value === "template") {
        composerMode.value = "normal";
    }

    if (!value) {
        composerMode.value = "template";
    }
});

watch(selectedTemplate, () => {
    templatePreviewOpen.value = false;

    initializeTemplatePreview();
});

const messageBorderClass = (message) => {
    if (message.direction !== "outbound") {
        return "border-surface-200";
    }

    switch (message.sender_context?.type) {
        case "assigned":
            return "border-emerald-400";

        case "old_owner":
            return "border-red-400";

        case "team_admin":
            return "border-blue-400";

        default:
            return "border-transparent";
    }
};
</script>

<template>
    <Head :title="customer.name" />

    <ExecutiveLayout :title="customer.name">
        <div class="h-[calc(120vh-80px)] flex flex-col min-h-0">
            <!-- Header -->
            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm shrink-0"
            >
                <div class="px-5 py-4 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <Link
                            :href="route('executive.messages.index')"
                            class="w-9 h-9 rounded-lg border border-surface-200 flex items-center justify-center text-surface-500 hover:bg-surface-50"
                        >
                            <ArrowLeft class="w-4 h-4" />
                        </Link>

                        <div
                            class="w-10 h-10 rounded-full bg-surface-100 flex items-center justify-center shrink-0"
                        >
                            <User class="w-5 h-5 text-surface-500" />
                        </div>

                        <div class="min-w-0">
                            <h1
                                class="text-sm font-semibold text-surface-900 truncate"
                            >
                                {{ customer.name }}
                            </h1>

                            <p class="text-xs text-surface-500 mt-0.5">
                                {{ customerPhone }}
                            </p>
                        </div>
                    </div>

                    <div class="hidden md:block text-right shrink-0">
                        <p class="text-xs text-surface-500">Workspace</p>

                        <p class="text-xs font-medium text-surface-800">
                            {{ customerTeam }}
                        </p>
                    </div>
                </div>

                <!-- Conversation Window -->
                <div
                    class="px-5 py-3 border-t border-surface-100"
                    :class="windowOpen ? 'bg-emerald-50' : 'bg-amber-50'"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <Clock
                                class="w-4 h-4"
                                :class="
                                    windowOpen
                                        ? 'text-emerald-600'
                                        : 'text-amber-600'
                                "
                            />

                            <div>
                                <p
                                    class="text-xs font-semibold"
                                    :class="
                                        windowOpen
                                            ? 'text-emerald-800'
                                            : 'text-amber-800'
                                    "
                                >
                                    {{
                                        windowOpen
                                            ? "24-hour messaging window is open"
                                            : "24-hour messaging window is closed"
                                    }}
                                </p>

                                <p
                                    class="text-[11px] mt-0.5"
                                    :class="
                                        windowOpen
                                            ? 'text-emerald-700'
                                            : 'text-amber-700'
                                    "
                                >
                                    {{
                                        windowOpen
                                            ? "You can send text, images, documents and other normal messages."
                                            : "A WhatsApp template is required to start or reopen this conversation."
                                    }}
                                </p>
                            </div>
                        </div>

                        <span
                            v-if="remainingTime"
                            class="text-xs font-medium text-emerald-700"
                        >
                            {{ remainingTime }}
                        </span>
                    </div>
                </div>

                <!-- Search / Date -->
                <div
                    class="px-4 py-2.5 border-t border-surface-100 flex flex-wrap items-center gap-2"
                >
                    <div class="relative flex-1 min-w-[220px]">
                        <Search
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-surface-400"
                        />

                        <input
                            v-model="searchText"
                            type="text"
                            placeholder="Search messages..."
                            class="w-full h-9 pl-9 pr-9 rounded-lg border border-surface-200 text-xs focus:border-slate-400 focus:ring-0"
                            @input="scheduleSearch"
                            @keydown.enter="performSearch"
                        />

                        <button
                            v-if="searchText"
                            type="button"
                            @click="clearSearch"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-700"
                        >
                            <X class="w-4 h-4" />
                        </button>

                        <LoaderCircle
                            v-if="searchLoading"
                            class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin text-surface-400"
                        />
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            @click="showDatePicker = !showDatePicker"
                            class="h-9 px-3 rounded-lg border border-surface-200 text-xs font-medium text-surface-600 hover:bg-surface-50 inline-flex items-center gap-2"
                        >
                            <Calendar class="w-4 h-4" />

                            Jump to date
                        </button>

                        <div
                            v-if="showDatePicker"
                            class="absolute z-30 right-0 top-11 bg-white border border-surface-200 rounded-xl shadow-xl p-3 w-64"
                        >
                            <p
                                class="text-xs font-semibold text-surface-800 mb-2"
                            >
                                Jump to date
                            </p>

                            <input
                                v-model="jumpDate"
                                type="date"
                                class="w-full rounded-lg border border-surface-200 text-sm focus:ring-0 focus:border-slate-400"
                                @keydown.enter="jumpToDate"
                            />

                            <div class="flex justify-end gap-2 mt-3">
                                <button
                                    type="button"
                                    @click="showDatePicker = false"
                                    class="px-3 py-1.5 rounded-lg text-xs text-surface-600 hover:bg-surface-50"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    @click="jumpToDate"
                                    :disabled="!jumpDate || loadingAroundDate"
                                    class="px-3 py-1.5 rounded-lg bg-slate-700 text-white text-xs font-medium disabled:opacity-50"
                                >
                                    {{
                                        loadingAroundDate ? "Loading..." : "Go"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="activeSearch"
                        class="text-[11px] text-surface-500"
                    >
                        {{ searchResults.length }}
                        result{{ searchResults.length === 1 ? "" : "s" }}
                    </div>
                </div>
            </div>

            <!-- Conversation -->
            <div
                ref="messagesContainer"
                class="relative flex-1 min-h-0 overflow-y-auto px-2 sm:px-4 py-5 space-y-3"
            >
                <div
                    v-if="loadingOlderMessages"
                    class="sticky top-0 z-20 flex justify-center pointer-events-none"
                >
                    <div
                        class="bg-white border border-surface-200 shadow-sm rounded-full px-3 py-1.5 flex items-center gap-2 text-[11px] text-surface-500"
                    >
                        <LoaderCircle class="w-3.5 h-3.5 animate-spin" />

                        Loading older messages...
                    </div>
                </div>

                <div
                    v-if="activeSearch"
                    class="sticky top-0 z-10 flex justify-center pointer-events-none"
                >
                    <div
                        class="bg-slate-800 text-white rounded-full px-3 py-1 text-[10px] shadow-sm"
                    >
                        Search results for "{{ activeSearch }}"
                    </div>
                </div>

                <div
                    v-if="!messageList.length"
                    class="h-full flex items-center justify-center"
                >
                    <div class="text-center">
                        <div
                            class="w-12 h-12 rounded-full bg-surface-100 flex items-center justify-center mx-auto"
                        >
                            <Search
                                v-if="activeSearch"
                                class="w-5 h-5 text-surface-400"
                            />

                            <User v-else class="w-5 h-5 text-surface-400" />
                        </div>

                        <p class="text-sm font-medium text-surface-700 mt-3">
                            {{
                                activeSearch
                                    ? "No messages found"
                                    : "No messages yet"
                            }}
                        </p>

                        <p class="text-xs text-surface-500 mt-1">
                            {{
                                activeSearch
                                    ? "Try another search term."
                                    : "Start the conversation using an approved template."
                            }}
                        </p>
                    </div>
                </div>

                <template
                    v-for="(message, index) in messageList"
                    :key="message.id"
                >
                    <div
                        v-if="shouldShowDateSeparator(index)"
                        class="flex items-center justify-center py-2"
                    >
                        <span
                            class="bg-surface-100 text-surface-500 text-[10px] font-medium px-3 py-1 rounded-full"
                        >
                            {{ formatFullDate(message.created_at) }}
                        </span>
                    </div>

                    <div
                        :data-message-id="message.id"
                        class="flex"
                        :class="
                            message.direction === 'outbound'
                                ? 'justify-end'
                                : 'justify-start'
                        "
                    >
                        <div
                            class="group relative rounded-2xl px-4 py-2.5 border-2 shadow-[0_1px_1px_rgba(0,0,0,0.04)]"
                            :class="[
                                message.document ? 'w-[40%]' : 'max-w-[60%]',

                                message.direction === 'outbound'
                                    ? 'bg-[#dffcd9] text-black rounded-br-md'
                                    : 'bg-white text-surface-900 rounded-bl-md',

                                messageBorderClass(message),
                            ]"
                        >
                            <!-- Media -->

                            <div
                                v-if="
                                    message.type &&
                                    message.type !== 'text' &&
                                    message.type !== 'chat' &&
                                    message.document
                                "
                                class="mb-2"
                            >
                                <div
                                    v-if="message.type === 'image'"
                                    class="relative rounded-xl bg-black/5 w-full h-64 bg-center bg-cover mx-auto"
                                    :style="{
                                        backgroundImage: `url(${message.document.url})`,
                                    }"
                                >
                                    <!-- Overlay actions -->
                                    <div
                                        class="absolute bottom-0 right-0 flex items-center gap-2 px-2 py-2 bg-black/40 text-white rounded-bl-xl"
                                    >
                                        <a
                                            :href="message.document.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-xs font-medium hover:underline"
                                        >
                                            View
                                        </a>

                                        <a
                                            :href="message.document.url"
                                            :download="
                                                message.document
                                                    .original_filename ||
                                                message.document.stored_filename
                                            "
                                            class="text-xs font-medium hover:underline"
                                        >
                                            Download
                                        </a>
                                    </div>
                                </div>

                                <div
                                    v-else-if="message.type === 'video'"
                                    class="overflow-hidden rounded-xl bg-black"
                                >
                                    <video
                                        :src="message.document.url"
                                        controls
                                        class="max-w-full max-h-80 mx-auto"
                                    />
                                </div>

                                <div
                                    v-else-if="message.type === 'audio'"
                                    class="rounded-xl p-3 bg-surface-50"
                                >
                                    <audio
                                        :src="message.document.url"
                                        controls
                                        class="w-full"
                                    />
                                </div>

                                <div
                                    v-else
                                    class="rounded-xl border p-3 min-w-[220px] border-surface-200 bg-surface-50"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 bg-white"
                                        >
                                            <FileText class="w-5 h-5" />
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="text-xs font-medium truncate"
                                            >
                                                {{
                                                    message.document
                                                        .original_filename ||
                                                    message.document
                                                        .stored_filename ||
                                                    "Document"
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex justify-end gap-3 mt-3">
                                        <a
                                            :href="message.document.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-xs font-medium hover:underline"
                                        >
                                            View
                                        </a>

                                        <a
                                            :href="message.document.url"
                                            :download="
                                                message.document
                                                    .original_filename ||
                                                message.document.stored_filename
                                            "
                                            class="text-xs font-medium hover:underline"
                                        >
                                            Download
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Body -->

                            <p
                                v-if="message.body"
                                class="text-sm whitespace-pre-wrap break-words"
                                v-html="
                                    activeSearch
                                        ? highlightSearchText(message.body)
                                        : message.body
                                "
                            />

                            <!-- Meta -->

                            <div
                                class="flex items-center justify-end gap-1 mt-1 text-surface-400"
                            >
                                <span class="text-[10px]">
                                    {{ formatTime(message.created_at) }}
                                </span>

                                <Check
                                    v-if="
                                        messageStatusIcon(message) === 'pending'
                                    "
                                    class="w-3 h-3"
                                />

                                <CheckCheck
                                    v-if="
                                        messageStatusIcon(message) ===
                                        'delivered'
                                    "
                                    class="w-3 h-3"
                                />

                                <CheckCheck
                                    v-if="messageStatusIcon(message) === 'read'"
                                    class="w-4 h-4 text-[#4FB6EC]"
                                />

                                <X
                                    v-if="
                                        messageStatusIcon(message) === 'failed'
                                    "
                                    class="w-3 h-3 text-red-500"
                                />
                            </div>

                            <!-- Sender information -->

                            <div
                                v-if="
                                    message.direction === 'outbound' &&
                                    message.sender_context?.name
                                "
                                class="absolute top-full right-0 mt-1 z-30 whitespace-nowrap px-2.5 py-1 rounded-md bg-surface-900 text-white text-[10px] font-medium shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 pointer-events-none"
                            >
                                {{ message.sender_context.name }}
                                ·
                                {{ message.sender_context.role }}
                            </div>
                        </div>
                    </div>
                </template>

                <button
                    v-if="showJumpToLatest"
                    type="button"
                    @click="scrollToBottom()"
                    class="sticky bottom-4 left-1/2 -translate-x-1/2 z-20 mx-auto flex items-center gap-2 bg-slate-800 text-white rounded-full px-4 py-2 text-xs font-medium shadow-lg hover:bg-slate-900"
                >
                    <ArrowDown class="w-3.5 h-3.5" />

                    Jump to latest
                </button>
            </div>

            <!-- Composer -->

            <div
                class="bg-white border border-surface-200 rounded-xl shadow-sm shrink-0"
            >
                <!-- Template selector -->

                <div v-if="composerMode === 'template'" class="p-4">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2">
                            <div
                                class="w-8 h-8 rounded-lg bg-surface-100 flex items-center justify-center"
                            >
                                <FileText class="w-4 h-4 text-surface-600" />
                            </div>

                            <div>
                                <p
                                    class="text-sm font-semibold text-surface-900"
                                >
                                    Send WhatsApp Template
                                </p>

                                <p class="text-xs text-surface-500 mt-0.5">
                                    Select a template and preview it before
                                    sending.
                                </p>
                            </div>
                        </div>

                        <button
                            v-if="windowOpen"
                            type="button"
                            @click="openNormalComposer"
                            :disabled="sending"
                            class="text-xs font-medium text-surface-600 hover:text-surface-900"
                        >
                            ← Normal message
                        </button>
                    </div>

                    <div
                        v-if="!templates.length"
                        class="rounded-lg border border-amber-200 bg-amber-50 p-3"
                    >
                        <p class="text-xs font-medium text-amber-800">
                            No approved WhatsApp templates are available.
                        </p>

                        <p class="text-[11px] text-amber-700 mt-1">
                            Please ask your administrator to configure an
                            approved template.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <select
                            v-model="selectedTemplate"
                            :disabled="sending"
                            class="w-full rounded-lg border border-surface-200 text-sm focus:border-surface-400 focus:ring-0"
                        >
                            <option :value="null">Select template</option>

                            <option
                                v-for="template in templates"
                                :key="template.id"
                                :value="template.id"
                            >
                                {{ template.name }}
                                —
                                {{ template.language }}
                            </option>
                        </select>

                        <div
                            v-if="selectedTemplateObject"
                            class="rounded-lg border border-surface-200 bg-surface-50 p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-semibold text-surface-800"
                                    >
                                        {{ selectedTemplateObject.name }}
                                    </p>

                                    <p
                                        class="text-[10px] text-surface-500 mt-0.5"
                                    >
                                        {{ selectedTemplateObject.language }}

                                        <span
                                            v-if="
                                                selectedTemplateObject.category
                                            "
                                        >
                                            ·
                                            {{
                                                selectedTemplateObject.category
                                            }}
                                        </span>
                                    </p>
                                </div>

                                <span
                                    class="shrink-0 text-[10px] font-medium px-2 py-1 rounded-full bg-emerald-100 text-emerald-700"
                                >
                                    APPROVED
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <button
                                v-if="windowOpen"
                                type="button"
                                @click="openNormalComposer"
                                :disabled="sending"
                                class="px-4 py-2 rounded-lg border border-surface-200 text-sm font-medium text-surface-700 hover:bg-surface-50 disabled:opacity-50"
                            >
                                Cancel
                            </button>

                            <button
                                type="button"
                                @click="openTemplatePreview"
                                :disabled="!selectedTemplate || sending"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-700 text-white text-sm font-medium hover:bg-slate-900 disabled:opacity-50"
                            >
                                <FileText class="w-4 h-4" />

                                Preview Template
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="!windowOpen"
                        class="text-[10px] text-amber-600 mt-3"
                    >
                        The 24-hour messaging window is closed. A template is
                        required to start or reopen the conversation.
                    </p>
                </div>

                <!-- Template Preview -->

                <div
                    v-if="templatePreviewOpen"
                    class="border-t border-surface-100 bg-surface-50"
                >
                    <div class="p-4">
                        <div
                            class="flex items-center justify-between gap-3 mb-4"
                        >
                            <div>
                                <p
                                    class="text-sm font-semibold text-surface-900"
                                >
                                    Preview Template
                                </p>

                                <p class="text-xs text-surface-500 mt-0.5">
                                    Review the message, variables and media
                                    before sending.
                                </p>
                            </div>

                            <button
                                type="button"
                                @click="closeTemplatePreview"
                                :disabled="sending"
                                class="w-8 h-8 rounded-lg border border-surface-200 bg-white flex items-center justify-center text-surface-500 hover:text-surface-900 disabled:opacity-50"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <!-- Variables -->

                            <div class="space-y-3">
                                <div
                                    v-if="hasTemplateBodyVariables"
                                    class="rounded-xl border border-surface-200 bg-white p-4"
                                >
                                    <p
                                        class="text-xs font-semibold text-surface-800 mb-3"
                                    >
                                        Body variables
                                    </p>

                                    <div class="space-y-3">
                                        <div
                                            v-for="(
                                                value, index
                                            ) in templateBodyVariables"
                                            :key="`body-${index}`"
                                        >
                                            <label
                                                class="block text-[11px] font-medium text-surface-600 mb-1"
                                            >
                                                Body variable
                                                {{ index + 1 }}
                                            </label>

                                            <input
                                                v-model="
                                                    templateBodyVariables[index]
                                                "
                                                type="text"
                                                maxlength="1000"
                                                :placeholder="`Value for {{${index + 1}}}`"
                                                class="w-full rounded-lg border border-surface-200 text-sm focus:border-surface-400 focus:ring-0"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="hasTemplateHeaderVariables"
                                    class="rounded-xl border border-surface-200 bg-white p-4"
                                >
                                    <p
                                        class="text-xs font-semibold text-surface-800 mb-3"
                                    >
                                        Header variables
                                    </p>

                                    <div class="space-y-3">
                                        <div
                                            v-for="(
                                                value, index
                                            ) in templateHeaderVariables"
                                            :key="`header-${index}`"
                                        >
                                            <label
                                                class="block text-[11px] font-medium text-surface-600 mb-1"
                                            >
                                                Header variable
                                                {{ index + 1 }}
                                            </label>

                                            <input
                                                v-model="
                                                    templateHeaderVariables[
                                                        index
                                                    ]
                                                "
                                                type="text"
                                                maxlength="1000"
                                                class="w-full rounded-lg border border-surface-200 text-sm focus:border-surface-400 focus:ring-0"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="hasTemplateHeaderMedia"
                                    class="rounded-xl border border-surface-200 bg-white p-4"
                                >
                                    <p
                                        class="text-xs font-semibold text-surface-800 mb-3"
                                    >
                                        Header media
                                    </p>

                                    <input
                                        v-model="templateHeaderMediaUrl"
                                        type="url"
                                        maxlength="2048"
                                        placeholder="https://example.com/file"
                                        class="w-full rounded-lg border border-surface-200 text-sm focus:border-surface-400 focus:ring-0"
                                    />
                                </div>

                                <div
                                    v-if="hasTemplateButtonVariables"
                                    class="rounded-xl border border-surface-200 bg-white p-4"
                                >
                                    <p
                                        class="text-xs font-semibold text-surface-800 mb-3"
                                    >
                                        Button variables
                                    </p>

                                    <div class="space-y-3">
                                        <div
                                            v-for="(
                                                item, index
                                            ) in templateButtonVariables"
                                            :key="`button-${index}`"
                                        >
                                            <label
                                                class="block text-[11px] font-medium text-surface-600 mb-1"
                                            >
                                                {{ item.label }}
                                            </label>

                                            <input
                                                v-model="item.value"
                                                type="text"
                                                maxlength="1000"
                                                class="w-full rounded-lg border border-surface-200 text-sm focus:border-surface-400 focus:ring-0"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview -->

                            <div
                                class="rounded-xl border border-surface-200 bg-[#efeae2] p-4"
                            >
                                <div
                                    class="flex items-center justify-between mb-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-semibold text-surface-800"
                                        >
                                            WhatsApp Preview
                                        </p>

                                        <p class="text-[10px] text-surface-500">
                                            {{ selectedTemplateObject?.name }}
                                        </p>
                                    </div>

                                    <span
                                        class="text-[10px] px-2 py-1 rounded-full bg-white/70 text-surface-600"
                                    >
                                        {{ selectedTemplateObject?.language }}
                                    </span>
                                </div>

                                <div class="flex justify-end">
                                    <div
                                        class="w-full max-w-[390px] bg-[#d9fdd3] rounded-xl rounded-tr-sm shadow-sm overflow-hidden"
                                    >
                                        <div
                                            v-if="
                                                templatePreviewHeader?.type ===
                                                    'image' &&
                                                templatePreviewHeader?.value
                                            "
                                        >
                                            <img
                                                :src="
                                                    templatePreviewHeader.value
                                                "
                                                alt="Template header"
                                                class="w-full max-h-56 object-cover"
                                            />
                                        </div>

                                        <div
                                            v-else-if="
                                                templatePreviewHeader?.type ===
                                                    'video' &&
                                                templatePreviewHeader?.value
                                            "
                                            class="bg-black"
                                        >
                                            <video
                                                :src="
                                                    templatePreviewHeader.value
                                                "
                                                controls
                                                class="w-full max-h-56 object-contain"
                                            />
                                        </div>

                                        <div
                                            v-else-if="
                                                templatePreviewHeader?.type ===
                                                    'document' &&
                                                templatePreviewHeader?.value
                                            "
                                            class="p-3"
                                        >
                                            <div
                                                class="rounded-lg border border-black/10 bg-white/50 p-3 flex items-center gap-3"
                                            >
                                                <FileText
                                                    class="w-6 h-6 text-surface-600"
                                                />

                                                <div class="min-w-0">
                                                    <p
                                                        class="text-xs font-medium text-surface-800"
                                                    >
                                                        Template document
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-if="
                                                templatePreviewHeader?.type ===
                                                    'text' &&
                                                templatePreviewHeader?.value
                                            "
                                            class="px-3 pt-3"
                                        >
                                            <p
                                                class="text-sm font-semibold text-surface-900 whitespace-pre-wrap break-words"
                                            >
                                                {{
                                                    templatePreviewHeader.value
                                                }}
                                            </p>
                                        </div>

                                        <div class="px-3 pt-3 pb-1">
                                            <p
                                                class="text-sm text-surface-900 whitespace-pre-wrap break-words"
                                            >
                                                {{
                                                    templatePreviewBody ||
                                                    "Template body preview"
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            v-if="templatePreviewButtons.length"
                                            class="px-3 pb-2 pt-2 space-y-1"
                                        >
                                            <div
                                                v-for="(
                                                    button, index
                                                ) in templatePreviewButtons"
                                                :key="index"
                                                class="text-center py-2 border-t border-black/10 text-xs font-medium text-blue-600"
                                            >
                                                {{ button.text || button.type }}
                                            </div>
                                        </div>

                                        <div class="px-3 pb-2 flex justify-end">
                                            <span
                                                class="text-[9px] text-surface-400"
                                            >
                                                now
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between gap-3 mt-4"
                        >
                            <p
                                v-if="templateHasMissingVariables"
                                class="text-[11px] text-amber-600"
                            >
                                Please complete all required template values
                                before sending.
                            </p>

                            <p v-else class="text-[11px] text-emerald-600">
                                Template is ready to send.
                            </p>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="closeTemplatePreview"
                                    :disabled="sending"
                                    class="px-4 py-2 rounded-lg border border-surface-200 bg-white text-sm font-medium text-surface-700 hover:bg-surface-50 disabled:opacity-50"
                                >
                                    Back
                                </button>

                                <button
                                    type="button"
                                    @click="sendTemplate"
                                    :disabled="
                                        sending ||
                                        !selectedTemplateObject ||
                                        templateHasMissingVariables
                                    "
                                    class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-slate-700 text-white text-sm font-medium hover:bg-slate-900 disabled:opacity-50"
                                >
                                    <LoaderCircle
                                        v-if="sending"
                                        class="w-4 h-4 animate-spin"
                                    />

                                    <Send v-else class="w-4 h-4" />

                                    {{
                                        sending ? "Sending..." : "Send Template"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Normal composer -->

                <div v-if="composerMode === 'normal'" class="p-4">
                    <div
                        v-if="selectedFile"
                        class="mb-3 flex items-center justify-between rounded-lg bg-surface-50 border border-surface-200 px-3 py-2"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <Paperclip
                                class="w-4 h-4 text-surface-500 shrink-0"
                            />

                            <span class="text-xs text-surface-700 truncate">
                                {{ selectedFile.name }}
                            </span>
                        </div>

                        <button
                            type="button"
                            @click="removeFile"
                            class="text-surface-400 hover:text-red-600"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="flex items-end gap-2">
                        <label
                            class="w-10 h-10 rounded-lg border border-surface-200 flex items-center justify-center cursor-pointer text-surface-500 hover:bg-surface-50"
                        >
                            <Paperclip class="w-4 h-4" />

                            <input
                                type="file"
                                class="hidden"
                                accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx"
                                @change="selectFile"
                            />
                        </label>

                        <textarea
                            v-model="messageText"
                            rows="2"
                            placeholder="Type a message..."
                            class="flex-1 resize-none rounded-lg border border-surface-200 text-sm focus:border-surface-400 focus:ring-0"
                            @keydown.enter.exact.prevent="sendCurrentMessage"
                        />

                        <button
                            v-if="selectedFile"
                            type="button"
                            @click="sendAttachment"
                            :disabled="sending"
                            class="w-10 h-10 rounded-lg bg-slate-700 text-white flex items-center justify-center hover:bg-slate-900 disabled:opacity-50"
                        >
                            <Send class="w-4 h-4" />
                        </button>

                        <button
                            v-else
                            type="button"
                            @click="sendTextMessage"
                            :disabled="!messageText.trim() || sending"
                            class="w-10 h-10 rounded-lg bg-slate-700 text-white flex items-center justify-center hover:bg-slate-900 disabled:opacity-50"
                        >
                            <Send class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="flex items-center justify-between gap-3 mt-2">
                        <p class="text-[10px] text-surface-400">
                            WhatsApp number:
                            {{ whatsappNumber || "Not assigned" }}
                        </p>

                        <button
                            type="button"
                            @click="openTemplateComposer"
                            :disabled="sending || !templates.length"
                            class="inline-flex items-center gap-1.5 text-[11px] font-medium text-surface-500 hover:text-surface-900 disabled:opacity-40"
                        >
                            <FileText class="w-3.5 h-3.5" />

                            Send template instead
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </ExecutiveLayout>
</template>
