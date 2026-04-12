<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { usePublicExpenseStore } from '../../Stores/publicExpense.js';
import { useToast } from '../../Composables/useToast.js';
import { useClipboard } from '../../Composables/useClipboard.js';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Card from '../../Components/Card.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';
import ExpenseHeader from '../../Components/ExpenseHeader.vue';
import PixCard from '../../Components/PixCard.vue';
import MemberList from '../../Components/MemberList.vue';
import WhatsAppShareButton from '../../Components/WhatsAppShareButton.vue';
import ProofViewerModal from '../../Components/ProofViewerModal.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    hash: { type: String, required: true },
    manage: { type: String, default: null },
});

const store = usePublicExpenseStore();
const toast = useToast();
const { copy } = useClipboard();

const proofModalOpen = ref(false);
const proofChargeId = ref(null);

const headerExpense = computed(() => {
    const e = store.expense;
    if (!e) return null;
    return { ...e, members: e.members ?? [] };
});

const publicLink = computed(() => {
    if (!store.expense?.public_hash) return null;
    return `${window.location.origin}/public/expenses/${store.expense.public_hash}`;
});

onMounted(async () => {
    store.reset();
    try {
        await store.fetchExpense(props.hash, props.manage || null);
    } catch {
        /* store.error */
    }
});

async function copyPublicLink() {
    if (!publicLink.value) return;
    await copy(publicLink.value);
}

async function validateCharge(chargeId) {
    if (!props.manage) return;
    try {
        await store.validateCharge(chargeId, props.manage);
        toast.success('Pagamento validado!');
        await store.fetchExpense(props.hash, props.manage);
    } catch {
        toast.error('Falha ao validar.');
    }
}

async function rejectCharge(chargeId) {
    if (!props.manage) return;
    try {
        await store.rejectCharge(chargeId, props.manage);
        toast.success('Comprovante rejeitado.');
        await store.fetchExpense(props.hash, props.manage);
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

async function resendMember(memberId) {
    if (!props.manage) return;
    try {
        const data = await store.resendParticipantLink(props.hash, memberId, props.manage);
        await copy(data.message);
        toast.success('Mensagem copiada! Cole no WhatsApp.');
    } catch (err) {
        toast.error(err.data?.message || 'Nao foi possivel gerar o link.');
    }
}
</script>

<template>
    <Head title="Despesa" />
    <div class="max-w-lg mx-auto pb-8">
        <Link href="/" class="text-sm text-indigo-600 hover:text-indigo-800 mb-4 inline-block min-h-[44px] inline-flex items-center">
            &larr; Inicio
        </Link>

        <LoadingSpinner v-if="store.loading && !store.expense" />

        <template v-else-if="store.expense && headerExpense">
            <p v-if="!store.expense.can_manage && !manage" class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-4">
                Guarde o link com <strong>?manage=...</strong> para validar pagamentos. Sem ele, esta tela e apenas informativa.
            </p>

            <Card class="mb-4">
                <ExpenseHeader :expense="headerExpense" />
            </Card>

            <div class="space-y-3 mb-4">
                <PixCard :pix-key="store.expense.pix_key" :pix-qr-code="store.expense.pix_qr_code || null" />
                <button
                    v-if="publicLink"
                    type="button"
                    class="w-full min-h-[48px] rounded-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-800 shadow-sm active:bg-gray-50"
                    @click="copyPublicLink"
                >
                    Copiar link do grupo
                </button>
                <WhatsAppShareButton
                    v-if="publicLink"
                    :description="store.expense.description"
                    :amount="store.expense.total_amount"
                    :public-url="publicLink"
                />
            </div>

            <Card title="Participantes">
                <MemberList
                    :members="store.expense.members || []"
                    :is-admin="!!store.expense.can_manage"
                    :show-resend="!!store.expense.can_manage"
                    @validate="validateCharge"
                    @reject="rejectCharge"
                    @view-proof="openProof"
                    @resend="resendMember"
                />
            </Card>
        </template>

        <Card v-else-if="store.error || !store.expense">
            <p class="text-center text-gray-600 py-8">{{ store.error || 'Despesa nao encontrada.' }}</p>
        </Card>

        <ProofViewerModal
            :show="proofModalOpen"
            :charge-id="proofChargeId"
            :manage-token="manage || null"
            @close="closeProof"
        />
    </div>
</template>
