<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-surface-900 via-surface-800 to-surface-900 p-4">
        <div class="w-full max-w-sm bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl text-white">
            <h1 class="text-xl font-bold">Two-factor verification</h1>
            <p class="text-sm text-surface-300 mt-2">Enter the code from your authenticator app to continue.</p>
            <form @submit.prevent="submit" class="mt-6 space-y-4">
                <input v-model="form.code" :placeholder="recovery ? 'Recovery code' : '6-digit code'" autocomplete="one-time-code" class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-center focus:ring-2 focus:ring-brand-400 focus:outline-none" />
                <p v-if="form.errors.code" class="text-sm text-red-400">{{ form.errors.code }}</p>
                <button class="w-full bg-brand-500 hover:bg-brand-600 py-3 rounded-xl font-semibold">Verify</button>
            </form>
            <button @click="recovery = !recovery; form.code = ''" class="mt-4 text-xs text-brand-300 hover:text-brand-200">{{ recovery ? 'Use authenticator code instead' : 'Use a recovery code instead' }}</button>
        </div>
    </div>
</template>
<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
const recovery = ref(false);
const form = useForm({ code: '', recovery: false });
const submit = () => { form.recovery = recovery.value; form.post(route('two-factor.verify')); };
</script>
