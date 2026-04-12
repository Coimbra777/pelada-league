<script setup>
defineProps({
    status: { type: String, required: true },
});

const statusConfig = {
    pending: { class: 'bg-red-100 text-red-800', label: 'Nao pagou', emoji: '\u{1F534}' },
    proof_sent: { class: 'bg-yellow-100 text-yellow-800', label: 'Aguardando validacao', emoji: '\u{1F7E1}' },
    validated: { class: 'bg-green-100 text-green-800', label: 'Pago', emoji: '\u{1F7E2}' },
    rejected: { class: 'bg-orange-100 text-orange-800', label: 'Rejeitado', emoji: '\u{26A0}\u{FE0F}' },
    open: { class: 'bg-yellow-100 text-yellow-800', label: 'Aberta', emoji: '' },
    closed: { class: 'bg-green-100 text-green-800', label: 'Fechada', emoji: '' },
};

function getConfig(status) {
    return statusConfig[status] || { class: 'bg-gray-100 text-gray-800', label: status, emoji: '' };
}
</script>

<template>
    <span
        :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium', getConfig(status).class]"
    >
        <span v-if="getConfig(status).emoji" aria-hidden="true">{{ getConfig(status).emoji }}</span>
        {{ getConfig(status).label }}
    </span>
</template>
