<script setup>
import { computed } from 'vue';
import { useDateBr } from '../Composables/useDateBr.js';

const props = defineProps({
    expense: { type: Object, required: true },
});

const { formatDateIsoToBr } = useDateBr();

const members = computed(() => {
    const m = props.expense.members;
    if (m?.length) return m;
    return (props.expense.participants || []).map((p) => ({
        name: p.name,
        charge_status: p.status,
    }));
});

const paidList = computed(() =>
    members.value.filter((row) => (row.charge_status || row.status) === 'validated'),
);

const unpaidList = computed(() =>
    members.value.filter((row) => (row.charge_status || row.status) !== 'validated'),
);

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
            <h3 class="text-base font-semibold text-gray-900">Resumo final</h3>
        </div>
        <div class="px-4 py-4 space-y-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Cabecalho</p>
                <p class="mt-1 text-lg font-bold text-gray-900">{{ expense.description }}</p>
                <dl class="mt-3 grid grid-cols-1 gap-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Valor total</dt>
                        <dd class="font-semibold text-gray-900">{{ formatCurrency(expense.total_amount) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Vencimento</dt>
                        <dd class="font-medium text-gray-900">{{ formatDateIsoToBr(expense.due_date) }}</dd>
                    </div>
                </dl>
            </div>

            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Estatisticas</p>
                <ul class="mt-2 space-y-1 text-sm text-gray-800">
                    <li>Total de participantes: <strong>{{ members.length }}</strong></li>
                    <li>Total pagos (validados): <strong>{{ paidList.length }}</strong></li>
                    <li>Total nao pagos: <strong>{{ unpaidList.length }}</strong></li>
                </ul>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-green-800 mb-2">Pagos</p>
                    <ul v-if="paidList.length" class="space-y-1.5 text-sm text-gray-900">
                        <li v-for="(row, i) in paidList" :key="'p-' + i" class="flex items-center gap-2">
                            <span class="text-green-600 font-medium" aria-hidden="true">&#10003;</span>
                            <span>{{ row.name }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500">Nenhum</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-amber-900 mb-2">Nao pagos</p>
                    <ul v-if="unpaidList.length" class="space-y-1.5 text-sm text-gray-900">
                        <li v-for="(row, i) in unpaidList" :key="'u-' + i" class="flex items-center gap-2">
                            <span class="text-amber-700 font-medium" aria-hidden="true">&#10007;</span>
                            <span>{{ row.name }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500">Nenhum</p>
                </div>
            </div>
        </div>
    </div>
</template>
