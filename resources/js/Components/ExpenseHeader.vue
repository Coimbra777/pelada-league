<script setup>
import StatusBadge from './StatusBadge.vue';
import { useDateBr } from '../Composables/useDateBr.js';

const props = defineProps({
    expense: { type: Object, required: true },
});

const { formatDateIsoToBr } = useDateBr();

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function paidCount() {
    return props.expense.members?.filter(m => m.charge_status === 'validated').length ?? 0;
}

function totalCount() {
    return props.expense.members?.length ?? 0;
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-bold text-gray-900">{{ expense.description }}</h2>
                <p class="text-sm text-gray-500 mt-1">Vencimento: {{ formatDateIsoToBr(expense.due_date) }}</p>
            </div>
            <StatusBadge :status="expense.status" />
        </div>
        <div class="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-3">
            <div>
                <p class="text-xs text-gray-500">Total</p>
                <p class="text-xl font-bold text-gray-900">{{ formatCurrency(expense.total_amount) }}</p>
            </div>
            <div v-if="expense.amount_per_member" class="text-right">
                <p class="text-xs text-gray-500">Por pessoa</p>
                <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(expense.amount_per_member) }}</p>
            </div>
        </div>
        <div v-if="expense.members" class="flex items-center gap-2">
            <div class="flex-1 bg-green-50 rounded-lg px-3 py-2 text-center">
                <span class="text-lg font-bold text-green-700">{{ paidCount() }}</span>
                <span class="text-sm text-green-600"> / {{ totalCount() }}</span>
                <p class="text-xs text-green-600">Pagos</p>
            </div>
        </div>
    </div>
</template>
