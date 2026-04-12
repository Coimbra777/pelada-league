<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { usePublicExpenseStore } from '../../Stores/publicExpense.js';
import { useToast } from '../../Composables/useToast.js';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Card from '../../Components/Card.vue';
import Input from '../../Components/Input.vue';
import Button from '../../Components/Button.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({ hash: String });

const store = usePublicExpenseStore();
const toast = useToast();

// Step: 'loading' | 'expense' | 'identify' | 'payment'
const step = ref('loading');
const nameInput = ref('');
const fileInput = ref(null);
const proofUploaded = ref(false);
const markedAsPaid = ref(false);

onMounted(async () => {
    store.reset();
    await store.fetchExpense(props.hash);
    step.value = store.expense ? 'expense' : 'error';
});

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function goToIdentify() {
    step.value = 'identify';
}

async function identifyMember() {
    if (!nameInput.value.trim()) return;
    try {
        const members = await store.identifyMember(props.hash, nameInput.value.trim());
        if (members.length === 1) {
            store.selectMember(members[0]);
            step.value = 'payment';
        }
        // If multiple matches, user selects from list (handled in template)
    } catch {
        // Error set in store
    }
}

function selectMember(member) {
    store.selectMember(member);
    step.value = 'payment';
}

async function uploadProof() {
    const file = fileInput.value?.files?.[0];
    if (!file) {
        toast.error('Selecione um arquivo.');
        return;
    }
    try {
        await store.uploadProof(store.selectedMember.charge_id, file);
        proofUploaded.value = true;
        toast.success('Comprovante enviado!');
    } catch {
        // Error set in store
    }
}

async function confirmPayment() {
    try {
        await store.markAsPaid(store.selectedMember.charge_id);
        markedAsPaid.value = true;
        toast.success('Pagamento confirmado! Aguardando validacao do admin.');
    } catch {
        // Error set in store
    }
}

const chargeStatus = computed(() => store.selectedMember?.status);
const alreadyPaid = computed(() =>
    ['proof_sent', 'validated'].includes(chargeStatus.value)
);
</script>

<template>
    <Head title="Despesa Compartilhada" />

    <!-- Loading -->
    <LoadingSpinner v-if="step === 'loading'" />

    <!-- Error -->
    <Card v-else-if="step === 'error'">
        <div class="text-center py-8">
            <p class="text-gray-500">{{ store.error || 'Despesa nao encontrada.' }}</p>
        </div>
    </Card>

    <!-- Step 1: View Expense -->
    <template v-else-if="step === 'expense'">
        <Card class="mb-4">
            <h2 class="text-lg font-bold text-gray-900 mb-2">{{ store.expense.description }}</h2>
            <div class="space-y-2 text-sm text-gray-600">
                <p>Total: <span class="font-semibold text-gray-900">{{ formatCurrency(store.expense.total_amount) }}</span></p>
                <p v-if="store.expense.amount_per_member">
                    Por membro: <span class="font-semibold text-gray-900">{{ formatCurrency(store.expense.amount_per_member) }}</span>
                </p>
                <p>Vencimento: <span class="font-semibold text-gray-900">{{ store.expense.due_date }}</span></p>
                <p>
                    Status: <StatusBadge :status="store.expense.status" />
                </p>
            </div>
        </Card>

        <Card v-if="store.expense.members?.length" title="Membros" class="mb-4">
            <div class="divide-y divide-gray-100">
                <div v-for="member in store.expense.members" :key="member.charge_id" class="py-2 flex justify-between items-center">
                    <span class="text-sm text-gray-900">{{ member.name }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(member.amount) }}</span>
                        <StatusBadge :status="member.charge_status" />
                    </div>
                </div>
            </div>
        </Card>

        <Button @click="goToIdentify" class="w-full">Quero pagar minha parte</Button>
    </template>

    <!-- Step 2: Identify -->
    <template v-else-if="step === 'identify'">
        <Card>
            <h2 class="text-lg font-bold text-gray-900 mb-4">Identifique-se</h2>
            <p class="text-sm text-gray-500 mb-4">Digite seu nome para encontrar sua cobranca.</p>
            <form @submit.prevent="identifyMember" class="space-y-4">
                <Input v-model="nameInput" label="Seu nome" placeholder="Ex: Maria" required />
                <p v-if="store.error" class="text-sm text-red-600">{{ store.error }}</p>
                <Button type="submit" :loading="store.loading" class="w-full">Buscar</Button>
            </form>

            <!-- Multiple matches -->
            <div v-if="store.members && store.members.length > 1" class="mt-4 space-y-2">
                <p class="text-sm text-gray-600">Encontramos mais de um resultado. Selecione:</p>
                <button
                    v-for="m in store.members"
                    :key="m.charge_id"
                    @click="selectMember(m)"
                    class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-indigo-50 rounded-lg border border-gray-200 transition-colors"
                >
                    <p class="text-sm font-medium text-gray-900">{{ m.name }}</p>
                    <p class="text-xs text-gray-500">{{ formatCurrency(m.amount) }} - <StatusBadge :status="m.status" /></p>
                </button>
            </div>
        </Card>

        <button @click="step = 'expense'" class="mt-4 text-sm text-indigo-600 hover:text-indigo-800">
            &larr; Voltar
        </button>
    </template>

    <!-- Step 3: Payment -->
    <template v-else-if="step === 'payment'">
        <!-- Already paid -->
        <Card v-if="alreadyPaid" class="mb-4">
            <div class="text-center py-4">
                <div class="text-3xl mb-2">&#10003;</div>
                <p class="text-lg font-semibold text-green-700">Pagamento ja registrado!</p>
                <p class="text-sm text-gray-500 mt-1">Status: <StatusBadge :status="chargeStatus" /></p>
            </div>
        </Card>

        <!-- Marked as paid success -->
        <Card v-else-if="markedAsPaid" class="mb-4">
            <div class="text-center py-4">
                <div class="text-3xl mb-2">&#10003;</div>
                <p class="text-lg font-semibold text-green-700">Pagamento confirmado!</p>
                <p class="text-sm text-gray-500 mt-1">Aguardando validacao do administrador.</p>
            </div>
        </Card>

        <!-- Payment flow -->
        <template v-else>
            <Card class="mb-4">
                <h2 class="text-lg font-bold text-gray-900 mb-2">
                    Ola, {{ store.selectedMember.name }}!
                </h2>
                <p class="text-sm text-gray-600 mb-4">
                    Seu valor: <span class="font-bold text-gray-900">{{ formatCurrency(store.selectedMember.amount) }}</span>
                </p>

                <!-- PIX info -->
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">Dados para pagamento PIX:</p>
                    <p class="text-sm text-gray-900 font-mono break-all">{{ store.expense.pix_key }}</p>
                    <div v-if="store.expense.pix_qr_code" class="mt-3 flex justify-center">
                        <img :src="`data:image/png;base64,${store.expense.pix_qr_code}`" alt="QR Code PIX" class="w-48 h-48" />
                    </div>
                </div>
            </Card>

            <!-- Upload proof -->
            <Card class="mb-4">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Enviar comprovante</h3>
                <div v-if="!proofUploaded">
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,application/pdf"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                    <p class="text-xs text-gray-400 mt-2">JPG, PNG ou PDF. Max 5MB.</p>
                    <p v-if="store.error" class="text-sm text-red-600 mt-2">{{ store.error }}</p>
                    <Button @click="uploadProof" :loading="store.loading" class="w-full mt-3">Enviar comprovante</Button>
                </div>
                <div v-else class="text-center py-2">
                    <p class="text-sm text-green-700 font-medium">Comprovante enviado com sucesso!</p>
                    <Button @click="confirmPayment" :loading="store.loading" class="w-full mt-3">Confirmar pagamento</Button>
                </div>
            </Card>
        </template>

        <button @click="step = 'expense'" class="mt-4 text-sm text-indigo-600 hover:text-indigo-800">
            &larr; Voltar para despesa
        </button>
    </template>
</template>
