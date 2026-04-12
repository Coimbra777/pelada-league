<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useExpenseStore } from '../../Stores/expenses.js';
import { useToast } from '../../Composables/useToast.js';
import { useClipboard } from '../../Composables/useClipboard.js';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/Card.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';
import ExpenseHeader from '../../Components/ExpenseHeader.vue';
import PixCard from '../../Components/PixCard.vue';
import MemberList from '../../Components/MemberList.vue';
import WhatsAppShareButton from '../../Components/WhatsAppShareButton.vue';
import ProofViewerModal from '../../Components/ProofViewerModal.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    teamId: [String, Number],
    id: [String, Number],
});

const expenseStore = useExpenseStore();
const toast = useToast();
const { copy } = useClipboard();

const proofModalOpen = ref(false);
const proofChargeId = ref(null);

onMounted(() => {
    expenseStore.fetchExpense(props.teamId, props.id);
});

const expense = computed(() => expenseStore.currentExpense);

const membersForList = computed(() => {
    const charges = expense.value?.charges;
    if (!charges?.length) return [];
    return charges.map((c) => ({
        charge_id: c.id,
        name: c.member?.name ?? 'Participante',
        phone: c.member?.phone,
        amount: c.amount,
        charge_status: c.status,
    }));
});

const headerExpense = computed(() => {
    const e = expense.value;
    if (!e) return null;
    return {
        ...e,
        members: membersForList.value,
    };
});

const publicLink = computed(() => expense.value?.public_url ?? null);

const isAdmin = computed(() => expense.value?.can_manage === true);

async function copyLink() {
    if (!publicLink.value) return;
    await copy(publicLink.value);
}

async function validateCharge(chargeId) {
    try {
        await expenseStore.validateCharge(chargeId);
        toast.success('Pagamento validado!');
        await expenseStore.fetchExpense(props.teamId, props.id);
    } catch {
        toast.error('Falha ao validar.');
    }
}

async function rejectCharge(chargeId) {
    try {
        await expenseStore.rejectCharge(chargeId);
        toast.success('Comprovante rejeitado.');
        await expenseStore.fetchExpense(props.teamId, props.id);
    } catch {
        toast.error('Falha ao rejeitar.');
    }
}

function openProof(chargeId) {
    proofChargeId.value = chargeId;
    proofModalOpen.value = true;
}

function closeProof() {
    proofModalOpen.value = false;
    proofChargeId.value = null;
}
</script>

<template>
    <Head title="Despesa" />
    <div class="max-w-lg mx-auto pb-8">
        <Link href="/dashboard" class="text-sm text-indigo-600 hover:text-indigo-800 mb-4 inline-block min-h-[44px] inline-flex items-center">
            &larr; Inicio
        </Link>

        <LoadingSpinner v-if="expenseStore.loading && !expense" />

        <template v-else-if="expense && headerExpense">
            <Card class="mb-4">
                <ExpenseHeader :expense="headerExpense" />
            </Card>

            <div v-if="publicLink" class="space-y-3 mb-4">
                <PixCard :pix-key="expense.pix_key" :pix-qr-code="expense.pix_qr_code || null" />
                <button
                    type="button"
                    class="w-full min-h-[48px] rounded-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-800 shadow-sm active:bg-gray-50"
                    @click="copyLink"
                >
                    Copiar link publico
                </button>
                <WhatsAppShareButton
                    :description="expense.description"
                    :amount="expense.total_amount"
                    :public-url="publicLink"
                />
            </div>

            <Card title="Participantes">
                <MemberList
                    :members="membersForList"
                    :is-admin="isAdmin"
                    @validate="validateCharge"
                    @reject="rejectCharge"
                    @view-proof="openProof"
                />
            </Card>
        </template>

        <ProofViewerModal :show="proofModalOpen" :charge-id="proofChargeId" @close="closeProof" />
    </div>
</template>
