<script setup>
import { useClipboard } from '../Composables/useClipboard.js';
import Button from './Button.vue';

defineProps({
    pixKey: { type: String, required: true },
    pixQrCode: { type: String, default: null },
});

const { copy } = useClipboard();
</script>

<template>
    <div class="bg-gray-50 rounded-xl p-4 space-y-3">
        <p class="text-sm font-medium text-gray-700">Pague via PIX</p>

        <div v-if="pixQrCode" class="flex justify-center">
            <img :src="`data:image/png;base64,${pixQrCode}`" alt="QR Code PIX" class="w-48 h-48 rounded-lg" />
        </div>

        <div class="flex items-center gap-2 bg-white rounded-lg border border-gray-200 px-3 py-2">
            <span class="flex-1 text-sm font-mono text-gray-900 truncate">{{ pixKey }}</span>
            <Button size="sm" variant="secondary" @click="copy(pixKey)">Copiar</Button>
        </div>
    </div>
</template>
