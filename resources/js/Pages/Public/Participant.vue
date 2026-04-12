<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { usePublicExpenseStore } from '../../Stores/publicExpense.js';
import { useToast } from '../../Composables/useToast.js';
import { useClipboard } from '../../Composables/useClipboard.js';
import { useDateBr } from '../../Composables/useDateBr.js';
import { formatPhoneBr } from '../../Composables/useInputMasks.js';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Card from '../../Components/Card.vue';
import Button from '../../Components/Button.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';
import PixCard from '../../Components/PixCard.vue';
import MemberList from '../../Components/MemberList.vue';
import UploadProof from '../../Components/UploadProof.vue';
import ExpenseHeader from '../../Components/ExpenseHeader.vue';
import WhatsAppShareButton from '../../Components/WhatsAppShareButton.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    /** Link curto /p/{hash} (despesa em grupo) */
    hash: { type: String, default: null },
    /** Link pessoal /p/{expenseHash}/{participantHash} */
    expenseHash: { type: String, default: null },
    participantHash: { type: String, default: null },
});

const store = usePublicExpenseStore();
const toast = useToast();
const { copy } = useClipboard();
const { formatDateIsoToBr } = useDateBr();

const isGroupMode = computed(() => !!props.hash && !props.participantHash);
const isDeepLinkMode = computed(() => !!props.expenseHash && !!props.participantHash);

/* --- Link pessoal (deep) --- */
const proofReady = ref(false);
const markingPaid = ref(false);
const bundle = computed(() => store.participantBundle);
const charge = computed(() => bundle.value?.charge);
const participant = computed(() => bundle.value?.participant);
const expenseDeep = computed(() => bundle.value?.expense);
const members = computed(() => bundle.value?.members ?? []);

const isExpenseClosedDeep = computed(() => expenseDeep.value?.status === 'closed');

const isGroupExpenseClosed = computed(() => store.expense?.status === 'closed');

const canUpload = computed(
    () =>
        charge.value
        && ['pending', 'rejected'].includes(charge.value.status)
        && !isExpenseClosedDeep.value,
);
const waitingApproval = computed(() => charge.value?.status === 'proof_sent');
const done = computed(() => charge.value?.status === 'validated');
const rejected = computed(() => charge.value?.status === 'rejected');

/* --- Modo grupo (/p/{hash}) --- */
const showParticipateForm = ref(false);
const participationDone = ref(false);
const participantName = ref('');
const participantPhone = ref('');
const proofFile = ref(null);
const proofFileInput = ref(null);
const participateErrors = ref({});
const participateSubmitting = ref(false);

function groupStorageKey() {
    return `public_participation_done_${props.hash}`;
}

const headerExpense = computed(() => {
    const e = store.expense;
    if (!e) return null;
    const membersList = e.members?.length
        ? e.members
        : (e.participants || []).map((p) => ({
              name: p.name,
              charge_status: p.status,
          }));
    return { ...e, members: membersList };
});

const participantRows = computed(() => store.expense?.participants ?? []);

const publicLink = computed(() => {
    if (!store.expense?.public_hash) return null;
    return `${window.location.origin}/p/${store.expense.public_hash}`;
});

function onProofFileChange(ev) {
    const f = ev.target.files?.[0];
    proofFile.value = f || null;
    participateErrors.value = { ...participateErrors.value, proof: null };
}

function onGroupPhoneInput(ev) {
    participantPhone.value = formatPhoneBr(ev.target.value);
}

async function submitGroupParticipation() {
    participateErrors.value = {};
    const name = participantName.value.trim();
    const phoneDigits = participantPhone.value.replace(/\D/g, '');
    if (!name) {
        participateErrors.value.name = 'Informe seu nome.';
        return;
    }
    if (phoneDigits.length < 10) {
        participateErrors.value.phone = 'Informe um telefone valido (min. 10 digitos).';
        return;
    }
    if (!proofFile.value) {
        participateErrors.value.proof = 'Selecione o comprovante.';
        return;
    }

    participateSubmitting.value = true;
    try {
        await store.participate(props.hash, {
            name,
            phone: phoneDigits,
            file: proofFile.value,
        });
        participationDone.value = true;
        showParticipateForm.value = false;
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem(groupStorageKey(), '1');
        }
        toast.success('Comprovante enviado! Aguardando aprovacao do responsavel.');
        participantName.value = '';
        participantPhone.value = '';
        proofFile.value = null;
        if (proofFileInput.value) proofFileInput.value.value = '';
        await store.fetchExpense(props.hash, null);
        console.log('Participate OK, expense atualizado', store.expense);
    } catch (err) {
        console.error('Participate erro', err);
        toast.error(err.data?.message || store.error || 'Nao foi possivel enviar.');
    } finally {
        participateSubmitting.value = false;
    }
}

onMounted(async () => {
    store.reset();
    console.log('Participant page props', {
        hash: props.hash,
        expenseHash: props.expenseHash,
        participantHash: props.participantHash,
        isGroupMode: isGroupMode.value,
        isDeepLinkMode: isDeepLinkMode.value,
    });

    if (isGroupMode.value) {
        if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(groupStorageKey()) === '1') {
            participationDone.value = true;
        }
        try {
            await store.fetchExpense(props.hash, null);
            console.log('GET public expense (grupo)', store.expense);
        } catch (err) {
            console.error('GET public expense falhou', err);
        }
        return;
    }

    if (isDeepLinkMode.value) {
        try {
            await store.fetchParticipantBundle(props.expenseHash, props.participantHash);
            console.log('GET participant bundle', store.participantBundle);
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
        } catch (err) {
            console.error('fetchParticipantBundle falhou', err);
        }
        return;
    }

    store.error = 'Link invalido.';
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
    if (!expenseDeep.value?.pix_key) return;
    await copy(expenseDeep.value.pix_key);
    toast.success('PIX copiado.');
}

async function copyPublicLinkGroup() {
    if (!publicLink.value) return;
    await copy(publicLink.value);
    toast.success('Link copiado.');
}
</script>

<template>
    <Head :title="isGroupMode ? 'Despesa' : 'Pagar minha parte'" />

    <div class="max-w-lg mx-auto pb-8">
        <Link href="/" class="text-sm text-indigo-600 hover:text-indigo-800 mb-4 inline-block min-h-[44px] inline-flex items-center">
            &larr; Inicio
        </Link>

        <!-- Modo grupo: /p/{hash} -->
        <template v-if="isGroupMode">
            <LoadingSpinner v-if="store.loading && !store.expense" />

            <template v-else-if="store.expense && headerExpense">
                <Card class="mb-4">
                    <ExpenseHeader :expense="headerExpense" />
                </Card>

                <div class="space-y-3 mb-4">
                    <PixCard :pix-key="store.expense.pix_key" :pix-qr-code="store.expense.pix_qr_code || null" />

                    <div
                        v-if="isGroupExpenseClosed"
                        class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-center"
                    >
                        <p class="text-sm font-semibold text-emerald-900">
                            Esta despesa ja foi finalizada pelo responsavel.
                        </p>
                        <p class="text-xs text-emerald-800/90 mt-2">
                            Nao e possivel enviar comprovantes por aqui.
                        </p>
                    </div>

                    <div
                        v-else-if="participationDone"
                        class="rounded-xl border border-green-200 bg-green-50 px-4 py-4 text-center"
                    >
                        <p class="text-sm font-medium text-green-900">
                            Aguardando aprovacao do responsavel
                        </p>
                        <p class="text-xs text-green-800/90 mt-2">
                            Seu comprovante foi enviado. Nao e possivel enviar outro por aqui.
                        </p>
                    </div>

                    <template v-else-if="!isGroupExpenseClosed">
                        <button
                            v-if="!showParticipateForm"
                            type="button"
                            class="w-full min-h-[48px] rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm active:bg-indigo-700"
                            @click="showParticipateForm = true"
                        >
                            Participar
                        </button>

                        <form
                            v-else
                            class="rounded-xl border border-gray-200 bg-white p-4 space-y-3 shadow-sm"
                            @submit.prevent="submitGroupParticipation"
                        >
                            <p class="text-sm font-medium text-gray-900">Enviar comprovante</p>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Nome (obrigatorio)</label>
                                <input
                                    v-model="participantName"
                                    type="text"
                                    autocomplete="name"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                    :class="participateErrors.name ? 'border-red-400' : ''"
                                />
                                <p v-if="participateErrors.name" class="text-xs text-red-600 mt-1">{{ participateErrors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Telefone (obrigatorio)</label>
                                <input
                                    :value="participantPhone"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    placeholder="(11) 99999-9999"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                    :class="participateErrors.phone ? 'border-red-400' : ''"
                                    @input="onGroupPhoneInput"
                                />
                                <p v-if="participateErrors.phone" class="text-xs text-red-600 mt-1">{{ participateErrors.phone }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Comprovante (obrigatorio)</label>
                                <input
                                    ref="proofFileInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/jpg,application/pdf"
                                    class="block w-full text-sm text-gray-600"
                                    :class="participateErrors.proof ? 'text-red-600' : ''"
                                    @change="onProofFileChange"
                                />
                                <p class="text-xs text-gray-500 mt-1">JPG, PNG ou PDF, ate 5 MB</p>
                                <p v-if="participateErrors.proof" class="text-xs text-red-600 mt-1">{{ participateErrors.proof }}</p>
                            </div>
                            <button
                                type="submit"
                                class="w-full min-h-[48px] rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white disabled:opacity-60"
                                :disabled="participateSubmitting"
                            >
                                {{ participateSubmitting ? 'Enviando...' : 'Enviar comprovante' }}
                            </button>
                        </form>
                    </template>

                    <button
                        v-if="publicLink"
                        type="button"
                        class="w-full min-h-[48px] rounded-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-800 shadow-sm active:bg-gray-50"
                        @click="copyPublicLinkGroup"
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
                    <ul class="divide-y divide-gray-100">
                        <li
                            v-for="(row, idx) in participantRows"
                            :key="idx"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <span class="text-sm font-medium text-gray-900 truncate">{{ row.name }}</span>
                            <StatusBadge :status="row.status" />
                        </li>
                        <li v-if="participantRows.length === 0" class="py-6 text-center text-sm text-gray-500">
                            Nenhum participante ainda
                        </li>
                    </ul>
                </Card>
            </template>

            <Card v-else-if="!store.loading && (store.error || !store.expense)">
                <p class="text-center text-gray-600 py-8">
                    {{ store.error || 'Despesa nao encontrada.' }}
                </p>
            </Card>
        </template>

        <!-- Modo link pessoal -->
        <template v-else-if="isDeepLinkMode">
            <LoadingSpinner v-if="store.loading && !bundle" />

            <div v-else-if="store.error || !bundle" class="text-center py-10 text-gray-600">
                {{ store.error || 'Link invalido.' }}
            </div>

            <template v-else>
                <Card class="mb-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ola</p>
                    <p class="text-xl font-bold text-gray-900">{{ participant?.name }}</p>
                    <p class="text-sm text-gray-600 mt-2">
                        {{ expenseDeep?.description }}
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                        Vencimento: {{ formatDateIsoToBr(expenseDeep?.due_date) }}
                    </p>
                    <p class="text-lg font-semibold text-gray-900 mt-3">
                        Sua parte: {{ formatCurrency(charge?.amount) }}
                    </p>
                    <button
                        v-if="expenseDeep?.pix_key"
                        type="button"
                        class="mt-3 text-xs text-indigo-600 font-medium"
                        @click="copyPix"
                    >
                        Copiar chave PIX
                    </button>
                </Card>

                <PixCard
                    v-if="expenseDeep"
                    :pix-key="expenseDeep.pix_key"
                    :pix-qr-code="expenseDeep.pix_qr_code || null"
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

                <Card v-else-if="isExpenseClosedDeep" class="mb-4 border-emerald-200 bg-emerald-50">
                    <div class="text-center py-4 space-y-2">
                        <p class="text-sm font-semibold text-emerald-900">
                            Esta despesa ja foi finalizada pelo responsavel.
                        </p>
                        <p class="text-xs text-emerald-800">
                            Nao e possivel enviar comprovantes nem alterar dados por aqui.
                        </p>
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

                <Card v-if="rejected" class="mb-4 border-orange-200 bg-orange-50">
                    <p class="text-sm text-orange-900 text-center py-2">
                        Comprovante rejeitado. Envie um novo arquivo abaixo.
                    </p>
                </Card>

                <Card v-if="members.length" title="Todos os participantes">
                    <MemberList :members="members" :is-admin="false" />
                </Card>
            </template>
        </template>

        <Card v-else>
            <p class="text-center text-gray-600 py-8">{{ store.error || 'Link invalido.' }}</p>
        </Card>
    </div>
</template>
