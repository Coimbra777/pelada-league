<script setup>
import { useToast } from '../Composables/useToast.js';

const { toasts } = useToast();

const typeClasses = {
    success: 'bg-green-50 text-green-800 border-green-200',
    error: 'bg-red-50 text-red-800 border-red-200',
    warning: 'bg-yellow-50 text-yellow-800 border-yellow-200',
};
</script>

<template>
    <div class="fixed top-4 right-4 z-[60] flex flex-col gap-2 max-w-sm">
        <TransitionGroup
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-x-4"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-4"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :class="['px-4 py-3 rounded-lg border shadow-sm text-sm font-medium', typeClasses[toast.type]]"
            >
                {{ toast.message }}
            </div>
        </TransitionGroup>
    </div>
</template>
