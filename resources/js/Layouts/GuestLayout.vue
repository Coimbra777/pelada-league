<script setup>
import { onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAuthStore } from '../Stores/auth.js';
import ToastContainer from '../Components/ToastContainer.vue';

const authStore = useAuthStore();

onMounted(() => {
    if (authStore.isAuthenticated) {
        router.visit('/dashboard');
    }
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-indigo-600">Caixinha</h1>
                <p class="mt-2 text-sm text-gray-500">Divida despesas com seu time</p>
            </div>
            <slot />
        </div>
        <ToastContainer />
    </div>
</template>
