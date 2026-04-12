<script setup>
defineProps({
    status: { type: String, required: true },
});

const statusConfig = {
    // Legacy Asaas (UPPERCASE)
    RECEIVED: { class: 'bg-green-100 text-green-800', label: 'Pago' },
    CONFIRMED: { class: 'bg-green-100 text-green-800', label: 'Confirmado' },
    RECEIVED_IN_CASH: { class: 'bg-green-100 text-green-800', label: 'Pago' },
    PAID: { class: 'bg-green-100 text-green-800', label: 'Pago' },
    PENDING: { class: 'bg-yellow-100 text-yellow-800', label: 'Pendente' },
    PARTIALLY_PAID: { class: 'bg-blue-100 text-blue-800', label: 'Parcial' },
    OVERDUE: { class: 'bg-red-100 text-red-800', label: 'Vencida' },
    // MVP (lowercase)
    pending: { class: 'bg-yellow-100 text-yellow-800', label: 'Pendente' },
    proof_sent: { class: 'bg-blue-100 text-blue-800', label: 'Comprovante Enviado' },
    validated: { class: 'bg-green-100 text-green-800', label: 'Validado' },
    rejected: { class: 'bg-red-100 text-red-800', label: 'Rejeitado' },
    open: { class: 'bg-yellow-100 text-yellow-800', label: 'Aberta' },
    closed: { class: 'bg-green-100 text-green-800', label: 'Fechada' },
};

function getConfig(status) {
    return statusConfig[status] || { class: 'bg-gray-100 text-gray-800', label: status };
}
</script>

<template>
    <span
        :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', getConfig(status).class]"
    >
        {{ getConfig(status).label }}
    </span>
</template>
