<script setup>
import { Link } from '@inertiajs/vue3';
import ToastContainer from '../Components/ToastContainer.vue';
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-lg mx-auto px-4 py-8">
            <div class="text-center mb-6">
                <Link href="/" class="text-2xl font-bold text-indigo-600">Caixinha</Link>
                <p class="mt-1 text-sm text-gray-500">Pagamento de despesa compartilhada</p>
            </div>
            <slot />
        </div>
        <ToastContainer />
    </div>
</template>
