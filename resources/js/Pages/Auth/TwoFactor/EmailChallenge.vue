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
                    Verify your identity
                </p>
            </div>

            <!-- Challenge card -->
            <div
                class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl"
            >
                <!-- Flash success -->
                <div
                    v-if="status"
                    class="mb-4 text-sm text-green-400 bg-green-400/10 rounded-xl px-4 py-3 border border-green-400/20"
                >
                    {{ status }}
                </div>

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
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4"
                            />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-white">
                        Two-factor verification
                    </h2>
                    <p class="text-xs text-amber-300 mt-2">
                        Enter the 6-digit code sent to your email address.
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <input
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            placeholder="000000"
                            autofocus
                            autocomplete="one-time-code"
                            :class="[
                                'w-full rounded-xl bg-white/10 border text-white placeholder-surface-500 px-4 py-3 text-center tracking-[0.3em] text-lg font-mono focus:outline-none focus:ring-2 focus:ring-brand-400 transition-all',
                                form.errors.code
                                    ? 'border-red-400'
                                    : 'border-white/10',
                            ]"
                        />
                        <p
                            v-if="form.errors.code"
                            class="text-xs text-red-400 mt-2"
                        >
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-brand-500 hover:bg-brand-600 disabled:opacity-60 text-white font-semibold py-2.5 rounded-xl transition-all text-sm shadow-lg shadow-brand-500/30 active:scale-[0.98]"
                    >
                        {{ form.processing ? "Verifying…" : "Verify" }}
                    </button>
                </form>

                <div class="mt-5 text-center">
                    <button
                        @click="resend"
                        :disabled="resendForm.processing"
                        class="text-xs text-gray-300 hover:text-gray-200 border border-gray-300 p-2.5 rounded-xl transition-colors disabled:opacity-50"
                    >
                        {{ resendForm.processing ? "Sending…" : "Resend code" }}
                    </button>
                </div>
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

const form = useForm({ code: "" });

const resendForm = useForm({});

function submit() {
    form.post(route("two-factor.verify"));
}

function resend() {
    resendForm.post(route("two-factor.resend"));
}
</script>
