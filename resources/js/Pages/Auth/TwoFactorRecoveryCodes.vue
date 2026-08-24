<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-surface-900 via-surface-800 to-surface-900 p-4">
        <div class="w-full max-w-lg bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl text-white">
            <h1 class="text-xl font-bold">Save your recovery codes</h1>
            <p class="text-sm text-surface-300 mt-2">Each code can be used once if you lose access to your authenticator. Store them somewhere secure.</p>
            <div class="mt-5 grid grid-cols-2 gap-2 font-mono text-sm bg-black/20 p-4 rounded-xl">
                <div v-for="code in codes" :key="code">{{ code }}</div>
            </div>
            <a :href="route('dashboard')" class="mt-6 block text-center w-full bg-brand-500 hover:bg-brand-600 py-3 rounded-xl font-semibold">I have saved my codes</a>
        </div>
    </div>
</template>
<script setup>defineProps({ codes: { type: Array, default: () => [] } });</script>
