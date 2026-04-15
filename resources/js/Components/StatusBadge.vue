<script setup>
import { getChargeStatusBadgeUx } from '../constants/chargeStatusUx.js';

const props = defineProps({
    status: { type: String, required: true },
    /** Cobrança: 'admin' = painel do responsável (rótulos Aguardando envio / Em análise / …). */
    chargePerspective: {
        type: String,
        default: 'participant',
        validator: (v) => ['participant', 'admin'].includes(v),
    },
});

const CHARGE_KEYS = new Set(['pending', 'proof_sent', 'validated', 'rejected']);

/** Despesa (Expense), não cobrança */
const expenseStatusConfig = {
    open: { class: 'bg-sky-50 text-sky-900 ring-1 ring-inset ring-sky-200', label: 'Aberta' },
    closed: { class: 'bg-emerald-50 text-emerald-900 ring-1 ring-inset ring-emerald-200', label: 'Finalizada' },
};

function getConfig(status) {
    if (CHARGE_KEYS.has(status)) {
        const { label, class: cls } = getChargeStatusBadgeUx(status, props.chargePerspective);
        return { class: cls, label };
    }
    if (expenseStatusConfig[status]) {
        return expenseStatusConfig[status];
    }
    return { class: 'bg-gray-100 text-gray-800 ring-1 ring-inset ring-gray-200', label: 'Situação em andamento' };
}
</script>

<template>
    <span
        :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', getConfig(status).class]"
    >
        {{ getConfig(status).label }}
    </span>
</template>
