<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useExpenseStore } from '../../Stores/expenses.js';
import { useToast } from '../../Composables/useToast.js';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/Card.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    teamId: [String, Number],
    id: [String, Number],
});

const expenseStore = useExpenseStore();
const toast = useToast();
const copied = ref(false);

onMounted(() => {
    expenseStore.fetchExpense(props.teamId, props.id);
});

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function publicUrl() {
    const hash = expenseStore.currentExpense?.public_hash;
    if (!hash) return null;
    return `${window.location.origin}/p/${hash}`;
}

async function copyPublicLink() {
    const url = publicUrl();
    if (!url) return;
    try {
        await navigator.clipboard.writeText(url);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch {
        toast.error('Falha ao copiar link.');
    }
}

async function validateCharge(chargeId) {
    try {
        await expenseStore.validateCharge(chargeId);
        toast.success('Comprovante validado!');
        expenseStore.fetchExpense(props.teamId, props.id);
    } catch {
        toast.error('Falha ao validar.');
    }
}

async function rejectCharge(chargeId) {
    try {
        await expenseStore.rejectCharge(chargeId);
        toast.success('Comprovante rejeitado.');
        expenseStore.fetchExpense(props.teamId, props.id);
    } catch {
        toast.error('Falha ao rejeitar.');
    }
}

function downloadProof(chargeId) {
    const token = localStorage.getItem('token');
    fetch(`/api/v1/charges/${chargeId}/proof`, {
        headers: { 'Authorization': `Bearer ${token}` },
    })
        .then(res => res.blob())
        .then(blob => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'comprovante';
            a.click();
            URL.revokeObjectURL(url);
        })
        .catch(() => toast.error('Falha ao baixar comprovante.'));
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
            <!-- Public link banner -->
            <div v-if="publicUrl()" class="mb-4 flex items-center gap-2 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3">
                <span class="text-sm text-indigo-700 flex-1 truncate">{{ publicUrl() }}</span>
                <button
                    @click="copyPublicLink"
                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800 whitespace-nowrap"
                >
                    {{ copied ? 'Copiado!' : 'Copiar link' }}
                </button>
            </div>

            <Card class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">{{ expenseStore.currentExpense.description }}</h1>
                        <p class="text-sm text-gray-500 mt-1">Vencimento: {{ expenseStore.currentExpense.due_date }}</p>
                        <p v-if="expenseStore.currentExpense.pix_key" class="text-sm text-gray-500 mt-1">
                            PIX: {{ expenseStore.currentExpense.pix_key }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(expenseStore.currentExpense.total_amount) }}</p>
                        <p v-if="expenseStore.currentExpense.amount_per_member" class="text-sm text-gray-500">
                            {{ formatCurrency(expenseStore.currentExpense.amount_per_member) }} / membro
                        </p>
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
                            <p class="text-sm font-medium text-gray-900">{{ charge.member?.name || 'Membro' }}</p>
                            <p class="text-xs text-gray-500">{{ charge.member?.phone }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(charge.amount) }}</p>
                            <StatusBadge :status="charge.status" />
                            <div class="flex gap-2">
                                <template v-if="charge.status === 'proof_sent'">
                                    <button @click="downloadProof(charge.id)" class="text-xs text-indigo-600 hover:text-indigo-800">
                                        Ver Comprovante
                                    </button>
                                    <button @click="validateCharge(charge.id)" class="text-xs text-green-600 hover:text-green-800 font-medium">
                                        Validar
                                    </button>
                                    <button @click="rejectCharge(charge.id)" class="text-xs text-red-600 hover:text-red-800 font-medium">
                                        Rejeitar
                                    </button>
                                </template>
                                <template v-else-if="charge.status === 'validated' || charge.status === 'rejected'">
                                    <button @click="downloadProof(charge.id)" class="text-xs text-indigo-600 hover:text-indigo-800">
                                        Ver Comprovante
                                    </button>
                                </template>
                                <template v-else-if="charge.pix_copy_paste">
                                    <Link :href="`/charges/${charge.id}`" class="text-xs text-indigo-600 hover:text-indigo-800">
                                        Ver PIX
                                    </Link>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </template>
    </div>
</template>
