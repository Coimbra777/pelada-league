<script setup>
import { useClipboard } from '../Composables/useClipboard.js';
import Button from './Button.vue';

const props = defineProps({
    description: { type: String, required: true },
    amount: { type: [Number, String], required: true },
    publicUrl: { type: String, required: true },
});

const { copy } = useClipboard();

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function buildMessage() {
    return `\u{26BD} ${props.description}\n\nValor: ${formatCurrency(props.amount)}\n\nPague via PIX e envie o comprovante:\n${props.publicUrl}`;
}

function copyMessage() {
    copy(buildMessage());
}
</script>

<template>
    <Button variant="secondary" size="md" @click="copyMessage" class="w-full">
        Copiar mensagem WhatsApp
    </Button>
</template>
