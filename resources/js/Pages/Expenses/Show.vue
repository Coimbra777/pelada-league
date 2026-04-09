<script setup>
import { onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useExpenseStore } from '../../Stores/expenses.js';
import { useToast } from '../../Composables/useToast.js';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/Card.vue';
import Button from '../../Components/Button.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    teamId: [String, Number],
    id: [String, Number],
});

const expenseStore = useExpenseStore();
const toast = useToast();

onMounted(() => {
    expenseStore.fetchExpense(props.teamId, props.id);
});

async function syncCharge(chargeId) {
    try {
        await expenseStore.syncCharge(chargeId);
        toast.success('Status atualizado!');
    } catch {
        toast.error('Falha ao sincronizar.');
    }
}

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
</script>

<template>
    <Head title="Despesa" />
    <div>
        <Link :href="`/teams/${props.teamId}`" class="text-sm text-indigo-600 hover:text-indigo-800 mb-4 inline-block">
            &larr; Voltar para equipe
        </Link>

        <LoadingSpinner v-if="expenseStore.loading && !expenseStore.currentExpense" />

        <template v-else-if="expenseStore.currentExpense">
            <Card class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">{{ expenseStore.currentExpense.description }}</h1>
                        <p class="text-sm text-gray-500 mt-1">Vencimento: {{ expenseStore.currentExpense.due_date }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(expenseStore.currentExpense.total_amount) }}</p>
                        <StatusBadge :status="expenseStore.currentExpense.status" />
                    </div>
                </div>
            </Card>

            <Card title="Cobrancas por Membro">
                <div class="divide-y divide-gray-100">
                    <div
                        v-for="charge in expenseStore.currentExpense.charges"
                        :key="charge.id"
                        class="py-3 flex items-center justify-between"
                    >
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ charge.member?.name || charge.user?.name || 'Membro' }}</p>
                            <p class="text-xs text-gray-500">{{ charge.member?.phone || charge.user?.email }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(charge.amount) }}</p>
                            <StatusBadge :status="charge.status" />
                            <div class="flex gap-2">
                                <Link
                                    v-if="charge.pix_copy_paste"
                                    :href="`/charges/${charge.id}`"
                                    class="text-xs text-indigo-600 hover:text-indigo-800"
                                >
                                    Ver PIX
                                </Link>
                                <button
                                    @click="syncCharge(charge.id)"
                                    class="text-xs text-gray-500 hover:text-gray-700"
                                >
                                    Sync
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </template>
    </div>
</template>
