<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Components/Layout/SuperAdminLayout.vue";

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
});

const components = props.template.components ?? [];

const header = components.find(
    (component) => (component.type ?? "").toUpperCase() === "HEADER",
);

const body = components.find(
    (component) => (component.type ?? "").toUpperCase() === "BODY",
);

const form = useForm({
    is_enabled: props.template.is_enabled,

    header_media_url: props.template.local_config?.header_media_url ?? "",

    variables: extractVariables(),
});

function extractVariables() {
    const variables = {};

    const text = body?.text ?? "";

    const matches = text.match(/\{\{\d+\}\}/g) ?? [];

    for (const variable of matches) {
        const number = variable.replace("{{", "").replace("}}", "");

        variables[number] =
            props.template.local_config?.variables?.[number] ?? "";
    }

    return variables;
}

function submit() {
    form.put(route("superadmin.whatsapp-templates.update", props.template.id));
}
</script>

<template>
    <Head :title="`Configure ${template.name}`" />

    <SuperAdminLayout :title="`Configure ${template.name}`">
        <div class="p-6 max-w-5xl">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-surface-900">
                        Configure Template
                    </h1>

                    <p class="mt-1 text-sm text-surface-500">
                        Configure application-level values without changing the
                        Meta-approved template.
                    </p>
                </div>

                <Link
                    :href="
                        route('superadmin.whatsapp-templates.show', template.id)
                    "
                    class="px-4 py-2 text-sm border border-slate-500 rounded-lg hover:bg-surface-50"
                >
                    ← Back
                </Link>
            </div>

            <!-- Warning -->
            <div
                class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4"
            >
                <p class="text-sm font-medium text-amber-800">
                    Meta template content is read-only
                </p>

                <p class="text-xs text-amber-700 mt-1">
                    The template name, body, language, category and approved
                    structure cannot be changed here. Only application-level
                    configuration can be modified.
                </p>
            </div>

            <div class="space-y-6">
                <!-- Meta information -->
                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Meta Template
                        </h2>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="text-xs text-surface-500">Name</p>

                            <p
                                class="text-sm font-medium text-surface-900 mt-1"
                            >
                                {{ template.name }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500">Language</p>

                            <p
                                class="text-sm font-medium text-surface-900 mt-1"
                            >
                                {{ template.language }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500">Category</p>

                            <p
                                class="text-sm font-medium text-surface-900 mt-1"
                            >
                                {{ template.category || "—" }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Enabled -->
                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="p-6">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                v-model="form.is_enabled"
                                type="checkbox"
                                class="rounded border-surface-300 text-slate-600 focus:ring-slate-500"
                            />

                            <div>
                                <p class="text-sm font-medium text-surface-900">
                                    Enable this template
                                </p>

                                <p class="text-xs text-surface-500 mt-0.5">
                                    Disabled templates cannot be selected for
                                    sending from the dashboard.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Header media -->
                <div
                    v-if="
                        header &&
                        ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(
                            (header.format ?? '').toUpperCase(),
                        )
                    "
                    class="bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Header Media
                        </h2>
                    </div>

                    <div class="p-6">
                        <p class="text-xs text-surface-500 mb-2">
                            {{ header.format }} URL
                        </p>

                        <input
                            v-model="form.header_media_url"
                            type="url"
                            :placeholder="`Enter ${header.format.toLowerCase()} URL`"
                            class="w-full rounded-lg border border-surface-200 px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-500"
                        />

                        <p
                            v-if="form.errors.header_media_url"
                            class="text-xs text-red-600 mt-1"
                        >
                            {{ form.errors.header_media_url }}
                        </p>

                        <div
                            v-if="
                                header.format === 'IMAGE' &&
                                form.header_media_url
                            "
                            class="mt-4"
                        >
                            <img
                                :src="form.header_media_url"
                                class="max-h-72 rounded-lg border border-surface-200 object-contain"
                            />
                        </div>
                    </div>
                </div>

                <!-- Variables -->
                <div
                    v-if="Object.keys(form.variables).length"
                    class="bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Variable Configuration
                        </h2>

                        <p class="text-xs text-surface-500 mt-1">
                            These values are used as application defaults or
                            mappings when the template is sent.
                        </p>
                    </div>

                    <div class="p-6 space-y-4">
                        <div
                            v-for="(value, number) in form.variables"
                            :key="number"
                        >
                            <label class="block text-xs font-medium text-surface-700 mb-1">
                                Variable {{ '{' }}{{ number }}{{ '}' }}
                            </label>


                            <input
                                v-model="form.variables[number]"
                                type="text"
                                :placeholder="`Value or mapping for {{${number}}}`"
                                class="w-full rounded-lg border border-surface-200 px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- Meta template preview -->
                <div
                    class="bg-white border border-surface-200 rounded-xl shadow-sm"
                >
                    <div class="px-6 py-4 border-b border-surface-200">
                        <h2 class="text-sm font-semibold text-surface-900">
                            Approved Template Content
                        </h2>
                    </div>

                    <div class="p-6">
                        <div
                            v-for="(component, index) in components"
                            :key="index"
                            class="mb-5 last:mb-0"
                        >
                            <p
                                class="text-xs font-medium text-surface-500 mb-1"
                            >
                                {{ component.type }}
                            </p>

                            <p
                                v-if="component.text"
                                class="text-sm text-surface-800 whitespace-pre-wrap"
                            >
                                {{ component.text }}
                            </p>

                            <p
                                v-else-if="component.format"
                                class="text-sm text-surface-600"
                            >
                                {{ component.format }}
                            </p>

                            <pre
                                v-else
                                class="text-xs bg-surface-50 p-3 rounded-lg overflow-x-auto"
                                >{{ JSON.stringify(component, null, 2) }}</pre
                            >
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Link
                        :href="
                            route(
                                'superadmin.whatsapp-templates.show',
                                template.id,
                            )
                        "
                        class="px-4 py-2 text-sm border border-surface-200 rounded-lg hover:bg-surface-50"
                    >
                        Cancel
                    </Link>

                    <button
                        @click="submit"
                        :disabled="form.processing"
                        class="px-5 py-2 text-sm font-medium text-white bg-slate-600 rounded-lg hover:bg-slate-900 disabled:opacity-50"
                    >
                        {{
                            form.processing ? "Saving..." : "Save Configuration"
                        }}
                    </button>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>