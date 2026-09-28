<template>
    <div
        class="min-h-screen flex items-center justify-center bg-gradient-to-br from-surface-900 via-surface-800 to-surface-900 p-4"
    >
        <!-- Background decoration -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div
                class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-brand-500/10 blur-3xl"
            />
            <div
                class="absolute -bottom-40 -left-40 w-96 h-96 rounded-full bg-brand-500/5 blur-3xl"
            />
        </div>

        <div class="relative w-full max-w-sm animate-slide-up">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div
                    class="inline-flex items-center justify-center w-[150px] h-12 mb-4 relative"
                >
                    <img
                        src="/assets/images/InTouchConnect.webp"
                        alt="InTouch Connect"
                        class="w-[150px] h-12 rounded-full object-cover absolute border-2 border-white shadow-lg shadow-brand-500/40"
                    />
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">
                    InTouch Connect
                </h1>
                <p class="text-sm text-surface-400 mt-1 text-white">
                    Two-factor authentication
                </p>
            </div>

            <!-- Setup card -->
            <div
                class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl"
            >
                <div class="text-center mb-6">
                    <div
                        class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-brand-500/20 mb-3"
                    >
                        <svg
                            class="w-6 h-6 text-brand-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-white">
                        Enable email two-factor authentication
                    </h2>
                    <p class="text-xs text-amber-300 mt-2">
                        A 6-digit verification code will be sent to your
                        registered email address each time you sign in.
                    </p>
                </div>

                <form @submit.prevent="submit">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-brand-500 hover:bg-brand-600 disabled:opacity-60 text-white font-semibold py-2.5 rounded-xl transition-all text-sm shadow-lg shadow-brand-500/30 active:scale-[0.98]"
                    >
                        {{
                            form.processing
                                ? "Enabling…"
                                : "Enable two-factor authentication"
                        }}
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-white mt-6">
                InTouch Connect · Powered by InTouch Software Solutions
            </p>
        </div>
    </div>
</template>

<script setup>
import { useForm } from "@inertiajs/vue3";

defineProps({ status: String });

const form = useForm({});

function submit() {
    form.post(route("two-factor.setup.store"));
}
</script>
