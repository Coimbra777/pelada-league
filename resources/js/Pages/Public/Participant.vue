<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { usePublicExpenseStore } from '../../Stores/publicExpense.js';
import { useToast } from '../../Composables/useToast.js';
import { useDateBr } from '../../Composables/useDateBr.js';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Card from '../../Components/Card.vue';
import Button from '../../Components/Button.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';
import PixCard from '../../Components/PixCard.vue';
import MemberList from '../../Components/MemberList.vue';
import UploadProof from '../../Components/UploadProof.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    expenseHash: { type: String, required: true },
    participantHash: { type: String, required: true },
});

const store = usePublicExpenseStore();
const toast = useToast();
const { formatDateIsoToBr } = useDateBr();

const proofReady = ref(false);
const markingPaid = ref(false);

const bundle = computed(() => store.participantBundle);
const charge = computed(() => bundle.value?.charge);
const participant = computed(() => bundle.value?.participant);
const expense = computed(() => bundle.value?.expense);
const members = computed(() => bundle.value?.members ?? []);

const canUpload = computed(() => charge.value && ['pending', 'rejected'].includes(charge.value.status));
const waitingApproval = computed(() => charge.value?.status === 'proof_sent');
const done = computed(() => charge.value?.status === 'validated');
const rejected = computed(() => charge.value?.status === 'rejected');

onMounted(async () => {
    store.reset();
    try {
        await store.fetchParticipantBundle(props.expenseHash, props.participantHash);
        const b = store.participantBundle;
        if (b?.charge && b?.participant) {
            store.selectMember({
                charge_id: b.charge.id,
                name: b.participant.name,
                phone: b.participant.phone,
                amount: b.charge.amount,
                status: b.charge.status,
            });
        }
    } catch {
        /* */
    }
});

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

async function afterProofUploaded() {
    proofReady.value = true;
}

async function markAsPaid() {
    if (!proofReady.value || !charge.value?.id) return;
    markingPaid.value = true;
    try {
        await store.markAsPaid(charge.value.id);
        toast.success('Enviado! Aguardando aprovacao.');
        await store.fetchParticipantBundle(props.expenseHash, props.participantHash);
        proofReady.value = false;
    } catch {
        toast.error(store.error || 'Falha ao confirmar.');
    } finally {
        markingPaid.value = false;
    }
}

async function copyPix() {
    if (!expense.value?.pix_key) return;
    await copy(expense.value.pix_key);
}
</script>

<template>
    <Head title="Pagar minha parte" />

    <LoadingSpinner v-if="store.loading && !bundle" />

    <div v-else-if="store.error || !bundle" class="text-center py-10 text-gray-600">
        {{ store.error || 'Link invalido.' }}
    </div>

    <template v-else>
        <Card class="mb-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ola</p>
            <p class="text-xl font-bold text-gray-900">{{ participant?.name }}</p>
            <p class="text-sm text-gray-600 mt-2">
                {{ expense?.description }}
            </p>
            <p class="text-sm text-gray-500 mt-1">
                Vencimento: {{ formatDateIsoToBr(expense?.due_date) }}
            </p>
            <p class="text-lg font-semibold text-gray-900 mt-3">
                Sua parte: {{ formatCurrency(charge?.amount) }}
            </p>
        </Card>

        <PixCard
            v-if="expense"
            :pix-key="expense.pix_key"
            :pix-qr-code="expense.pix_qr_code || null"
            class="mb-4"
        />

        <Card v-if="done" class="mb-4">
            <div class="text-center py-4">
                <p class="text-lg font-semibold text-green-700">Pagamento confirmado!</p>
                <p class="text-sm text-gray-500 mt-1">Obrigado.</p>
            </div>
        </Card>

        <Card v-else-if="waitingApproval" class="mb-4">
            <div class="text-center py-4 space-y-2">
                <StatusBadge status="proof_sent" />
                <p class="text-sm text-gray-700 font-medium">Aguardando aprovacao do responsavel.</p>
                <p class="text-xs text-gray-500">Voce nao precisa enviar outro comprovante.</p>
            </div>
        </Card>

        <template v-else-if="canUpload && charge">
            <Card title="Enviar comprovante" class="mb-4">
                <UploadProof :charge-id="charge.id" @uploaded="afterProofUploaded">
                    <template #after-upload>
                        <Button
                            class="w-full min-h-[48px]"
                            size="lg"
                            :disabled="!proofReady"
                            :loading="markingPaid"
                            @click="markAsPaid"
                        >
                            Marcar como pago
                        </Button>
                        <p v-if="!proofReady" class="text-xs text-gray-500 mt-2 text-center">
                            Envie o comprovante para habilitar.
                        </p>
                    </template>
                </UploadProof>
            </Card>
        </template>

        <Card v-if="members.length" title="Todos os participantes">
            <MemberList :members="members" :is-admin="false" />
        </Card>
    </template>
</template>
