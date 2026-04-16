<script setup>
import ToastContainer from '../Components/ToastContainer.vue';
</script>

<template>
    <div class="min-h-screen bg-stone-100 text-gray-900">
        <main class="max-w-[400px] mx-auto px-4 py-8 pb-12 w-full">
            <slot />
        </main>
        <ToastContainer />
    </div>
</template>
