<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-surface-900 via-surface-800 to-surface-900 p-4">
        <div class="w-full max-w-md bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl text-white">
            <h1 class="text-xl font-bold">Secure your account</h1>
            <p class="text-sm text-surface-300 mt-2">Two-factor authentication is mandatory for all CRM accounts. Scan the QR code using Google Authenticator, Microsoft Authenticator, or another compatible authenticator app.</p>

            <div v-if="!configured" class="mt-6 space-y-5">
                <div class="bg-white rounded-xl p-3 mx-auto w-fit">
                    <img :src="qrCode" alt="Two-factor QR code" class="w-56 h-56" />
                </div>
                <p class="text-xs text-surface-300 break-all text-center">Account: {{ email }}</p>
                <details class="text-xs text-surface-300"><summary class="cursor-pointer">Can't scan the QR code?</summary><p class="mt-2 break-all">Enter this setup key manually: <strong>{{ secret }}</strong></p></details>
                <form @submit.prevent="submit" class="space-y-3">
                    <input v-model="form.code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="Enter 6-digit code" class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-center tracking-[0.35em] focus:ring-2 focus:ring-brand-400 focus:outline-none" />
                    <p v-if="form.errors.code" class="text-sm text-red-400">{{ form.errors.code }}</p>
                    <button :disabled="form.processing" class="w-full bg-brand-500 hover:bg-brand-600 py-3 rounded-xl font-semibold disabled:opacity-60">{{ form.processing ? 'Verifying…' : 'Verify & Enable 2FA' }}</button>
                </form>
            </div>
        </div>
    </div>
</template>
<script setup>
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ configured: Boolean, qrCode: String, secret: String, email: String });
const form = useForm({ code: '' });
const submit = () => form.post(route('two-factor.confirm'));
</script>
