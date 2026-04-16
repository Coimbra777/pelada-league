<script setup>
defineProps({
    title: { type: String, default: '' },
    padding: { type: Boolean, default: true },
});
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div v-if="title || $slots.header" class="px-4 py-3 sm:px-6 border-b border-gray-100">
            <slot name="header">
                <h3 class="text-base font-semibold text-gray-900">{{ title }}</h3>
            </slot>
        </div>
        <div :class="padding ? 'p-4 sm:p-6' : ''">
            <slot />
        </div>
        <div v-if="$slots.footer" class="px-4 py-3 sm:px-6 border-t border-gray-100 bg-gray-50">
            <slot name="footer" />
        </div>
    </div>
</template>
