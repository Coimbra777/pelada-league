<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { usePublicExpenseStore } from '../../Stores/publicExpense.js';
import { useToast } from '../../Composables/useToast.js';
import { useClipboard } from '../../Composables/useClipboard.js';
import { formatPhoneBr, maskCurrencyFromDigits, parseCurrencyBrToNumber } from '../../Composables/useInputMasks.js';
import { useDateBr } from '../../Composables/useDateBr.js';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import Card from '../../Components/Card.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';
import ExpenseHeader from '../../Components/ExpenseHeader.vue';
import PixCard from '../../Components/PixCard.vue';
import MemberList from '../../Components/MemberList.vue';
import WhatsAppShareButton from '../../Components/WhatsAppShareButton.vue';
import ProofViewerModal from '../../Components/ProofViewerModal.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import Modal from '../../Components/Modal.vue';
import Button from '../../Components/Button.vue';
import ExpenseSummary from '../../Components/ExpenseSummary.vue';
import { getByHash, saveExpense } from '../../Services/localExpenses.js';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    hash: { type: String, required: true },
    manage: { type: String, default: null },
});

const store = usePublicExpenseStore();
const toast = useToast();
const { copy } = useClipboard();
const { formatDateIsoToBr } = useDateBr();

const proofModalOpen = ref(false);
const proofChargeId = ref(null);

const showParticipateForm = ref(false);
const participationDone = ref(false);
const participantName = ref('');
const participantPhone = ref('');
const proofFile = ref(null);
const proofFileInput = ref(null);
const participateErrors = ref({});
const participateSubmitting = ref(false);

function participationStorageKey() {
    return `public_participation_done_${props.hash}`;
}

const headerExpense = computed(() => {
    const e = store.expense;
    if (!e) return null;
    const members = e.members?.length
        ? e.members
        : (e.participants || []).map((p) => ({
              name: p.name,
              charge_status: p.status,
          }));
    return { ...e, members };
});

const participantRows = computed(() => store.expense?.participants ?? []);

const publicLink = computed(() => {
    if (!store.expense?.public_hash) return null;
    return `${window.location.origin}/p/${store.expense.public_hash}`;
});

const isParticipantMode = computed(() => !props.manage);

const isAdminDashboard = computed(() => !!props.manage && !!store.expense?.can_manage);

const isClosed = computed(() => store.expense?.status === 'closed');

const adminMembers = computed(() => store.expense?.members ?? []);

const canFinalizeExpense = computed(() => {
    const m = adminMembers.value;
    return m.length > 0 && m.every((x) => x.charge_status === 'validated');
});

const showFinalizeButton = computed(() => isAdminDashboard.value && !isClosed.value);

const moderationEnabled = computed(() => isAdminDashboard.value && !isClosed.value);

const showCloseConfirmModal = ref(false);
const closeSubmitting = ref(false);

const showEditModal = ref(false);
const editDescription = ref('');
const editAmount = ref('');
const editDueDate = ref('');
const editPixKey = ref('');
const editPixQr = ref('');
const editSaving = ref(false);
const editFieldErrors = ref({});

const participantsText = ref('');
const addParticipantName = ref('');
const addParticipantPhone = ref('');
const addSubmitting = ref(false);
const addParticipantsError = ref('');

function parseBrDateToIso(s) {
    const m = String(s).trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!m) return null;
    return `${m[3]}-${m[2]}-${m[1]}`;
}

function openEditModal() {
    const e = store.expense;
    if (!e) return;
    editDescription.value = e.description || '';
    const cents = Math.round(Number(e.total_amount) * 100);
    editAmount.value = maskCurrencyFromDigits(String(cents));
    editDueDate.value = formatDateIsoToBr(e.due_date);
    editPixKey.value = e.pix_key || '';
    editPixQr.value = e.pix_qr_code || '';
    editFieldErrors.value = {};
    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
}

function onEditAmountInput(ev) {
    editAmount.value = maskCurrencyFromDigits(ev.target.value);
}

async function saveExpenseEdit() {
    editFieldErrors.value = {};
    const iso = parseBrDateToIso(editDueDate.value);
    if (!iso) {
        editFieldErrors.value.due_date = 'Use DD/MM/AAAA.';
        return;
    }
    const amountNum = parseCurrencyBrToNumber(editAmount.value);
    if (!Number.isFinite(amountNum) || amountNum < 0.01) {
        editFieldErrors.value.amount = 'Informe um valor valido.';
        return;
    }
    if (!editDescription.value.trim()) {
        editFieldErrors.value.description = 'Informe a descricao.';
        return;
    }
    if (!editPixKey.value.trim()) {
        editFieldErrors.value.pix_key = 'Informe a chave PIX.';
        return;
    }

    editSaving.value = true;
    try {
        await store.patchExpense(props.hash, props.manage, {
            description: editDescription.value.trim(),
            amount: amountNum,
            due_date: iso,
            pix_key: editPixKey.value.trim(),
            pix_qr_code: editPixQr.value.trim() || null,
        });
        toast.success('Despesa atualizada.');
        closeEditModal();
        await store.fetchExpense(props.hash, props.manage);
        const existing = getByHash(props.hash);
        if (existing && store.expense) {
            saveExpense({
                hash: store.expense.public_hash,
                manage_token: props.manage,
                description: store.expense.description,
                amount: Number(store.expense.total_amount),
                created_at: existing.created_at,
            });
        }
    } catch (err) {
        const msg = err.data?.message
            || (err.data?.errors ? Object.values(err.data.errors).flat().join(' ') : null)
            || 'Nao foi possivel salvar.';
        toast.error(msg);
    } finally {
        editSaving.value = false;
    }
}

function onAddParticipantPhoneInput(ev) {
    addParticipantPhone.value = formatPhoneBr(ev.target.value);
}

async function submitAddParticipants() {
    addParticipantsError.value = '';
    const phoneDigits = addParticipantPhone.value.replace(/\D/g, '');
    const hasSingle = addParticipantName.value.trim().length > 0 && phoneDigits.length >= 10;
    const hasBulk = participantsText.value.trim().length > 0;

    if (!hasSingle && !hasBulk) {
        addParticipantsError.value = 'Preencha nome e telefone ou cole uma lista abaixo.';
        return;
    }

    const payload = {};
    if (hasSingle) {
        payload.participants = [{ name: addParticipantName.value.trim(), phone: phoneDigits }];
    }
    if (hasBulk) {
        payload.participants_text = participantsText.value;
    }

    addSubmitting.value = true;
    try {
        await store.addExpenseParticipants(props.hash, props.manage, payload);
        addParticipantName.value = '';
        addParticipantPhone.value = '';
        participantsText.value = '';
        toast.success('Participantes adicionados.');
        await store.fetchExpense(props.hash, props.manage);
    } catch (err) {
        addParticipantsError.value = err.data?.message
            || (err.data?.errors ? Object.values(err.data.errors).flat().join(' ') : 'Falha ao adicionar.');
        toast.error(addParticipantsError.value);
    } finally {
        addSubmitting.value = false;
    }
}

async function copyParticipantUrl(url) {
    if (!url) return;
    await copy(url);
    toast.success('Link copiado.');
}

function onProofFileChange(ev) {
    const f = ev.target.files?.[0];
    proofFile.value = f || null;
    participateErrors.value = { ...participateErrors.value, proof: null };
}

function onPhoneInput(ev) {
    participantPhone.value = formatPhoneBr(ev.target.value);
}

onMounted(async () => {
    if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(participationStorageKey()) === '1') {
        participationDone.value = true;
    }

    if (!props.manage) {
        const stored = getByHash(props.hash);
        if (stored?.manage_token) {
            router.replace(
                `/public/expenses/${props.hash}?manage=${encodeURIComponent(stored.manage_token)}`,
            );
            return;
        }
    }

    store.reset();
    try {
        await store.fetchExpense(props.hash, props.manage || null);
        if (store.expense?.can_manage && props.manage) {
            const existing = getByHash(props.hash);
            saveExpense({
                hash: store.expense.public_hash,
                manage_token: props.manage,
                description: store.expense.description,
                amount: Number(store.expense.total_amount),
                created_at: existing?.created_at || new Date().toISOString(),
            });
        }
    } catch {
        /* store.error */
    }
});

async function copyPublicLink() {
    if (!publicLink.value) return;
    await copy(publicLink.value);
}

async function submitParticipation() {
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
            sessionStorage.setItem(participationStorageKey(), '1');
        }
        toast.success('Comprovante enviado! Aguardando aprovacao do responsavel.');
        participantName.value = '';
        participantPhone.value = '';
        proofFile.value = null;
        if (proofFileInput.value) proofFileInput.value.value = '';
        await store.fetchExpense(props.hash, null);
    } catch (err) {
        toast.error(err.data?.message || store.error || 'Nao foi possivel enviar.');
    } finally {
        participateSubmitting.value = false;
    }
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

function openCloseConfirm() {
    if (!canFinalizeExpense.value) return;
    showCloseConfirmModal.value = true;
}

async function confirmCloseExpense() {
    if (!props.manage || !canFinalizeExpense.value) return;
    closeSubmitting.value = true;
    try {
        await store.closeExpense(props.hash, props.manage);
        toast.success('Despesa finalizada. Nenhuma alteracao adicional e permitida.');
        showCloseConfirmModal.value = false;
        await store.fetchExpense(props.hash, props.manage);
    } catch (err) {
        toast.error(err.data?.message || 'Nao foi possivel finalizar.');
    } finally {
        closeSubmitting.value = false;
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
            <Card class="mb-4">
                <ExpenseHeader :expense="headerExpense" />
                <p
                    v-if="isClosed"
                    class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900"
                >
                    Despesa finalizada — apenas visualizacao.
                </p>
            </Card>

            <div v-if="isClosed && isAdminDashboard && headerExpense" class="mb-4">
                <ExpenseSummary :expense="headerExpense" />
            </div>

            <!-- Participante: PIX + participar -->
            <template v-if="isParticipantMode">
                <div class="space-y-3 mb-4">
                    <PixCard :pix-key="store.expense.pix_key" :pix-qr-code="store.expense.pix_qr_code || null" />

                    <div
                        v-if="isClosed"
                        class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-center"
                    >
                        <p class="text-sm font-semibold text-emerald-900">
                            Esta despesa ja foi finalizada pelo responsavel.
                        </p>
                        <p class="text-xs text-emerald-800/90 mt-2">
                            Nao e possivel enviar comprovantes nem alterar dados por aqui.
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

                    <template v-else-if="!isClosed">
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
                            @submit.prevent="submitParticipation"
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
                                    @input="onPhoneInput"
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
                        @click="copyPublicLink"
                    >
                        Copiar link para participantes
                    </button>
                    <WhatsAppShareButton
                        v-if="publicLink"
                        :description="store.expense.description"
                        :amount="store.expense.total_amount"
                        :public-url="publicLink"
                    />
                </div>
            </template>

            <!-- Admin: editar, novos participantes, compartilhar, PIX -->
            <div v-else class="space-y-4 mb-4">
                <div v-if="isAdminDashboard" class="space-y-3">
                    <div v-if="showFinalizeButton" class="space-y-1">
                        <button
                            type="button"
                            class="w-full min-h-[48px] rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm active:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!canFinalizeExpense"
                            :title="!canFinalizeExpense ? 'Ainda existem participantes pendentes' : ''"
                            @click="openCloseConfirm"
                        >
                            Finalizar despesa
                        </button>
                        <p v-if="!canFinalizeExpense" class="text-xs text-amber-800">
                            Finalizar so fica disponivel quando todos os pagamentos estiverem validados.
                        </p>
                    </div>

                    <template v-if="!isClosed">
                        <button
                            type="button"
                            class="w-full min-h-[48px] rounded-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-800 shadow-sm active:bg-gray-50"
                            @click="openEditModal"
                        >
                            Editar despesa
                        </button>

                        <Card title="Adicionar participantes">
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome</label>
                                    <input
                                        v-model="addParticipantName"
                                        type="text"
                                        autocomplete="name"
                                        class="w-full min-h-[44px] rounded-xl border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Telefone</label>
                                    <input
                                        :value="addParticipantPhone"
                                        type="tel"
                                        inputmode="tel"
                                        autocomplete="tel"
                                        placeholder="(11) 99999-9999"
                                        class="w-full min-h-[44px] rounded-xl border border-gray-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        @input="onAddParticipantPhoneInput"
                                    />
                                </div>
                                <p class="text-xs text-gray-500">
                                    Opcional: cole varias linhas (estilo WhatsApp), uma pessoa por linha —
                                    <span class="font-mono">Nome 11999998888</span>
                                </p>
                                <textarea
                                    v-model="participantsText"
                                    rows="3"
                                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm min-h-[80px] focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="Maria Silva 11988776655"
                                />
                                <p v-if="addParticipantsError" class="text-sm text-red-600">{{ addParticipantsError }}</p>
                                <button
                                    type="button"
                                    class="w-full min-h-[48px] rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white disabled:opacity-50"
                                    :disabled="addSubmitting"
                                    @click="submitAddParticipants"
                                >
                                    {{ addSubmitting ? 'Adicionando...' : 'Adicionar' }}
                                </button>
                            </div>
                        </Card>
                    </template>
                </div>

                <div v-if="store.expense.can_manage && publicLink && !isClosed" class="rounded-xl border border-gray-200 bg-white px-4 py-4 space-y-3">
                    <p class="text-sm text-gray-700 leading-relaxed">
                        Compartilhe este link com os participantes para que eles possam pagar
                    </p>
                    <button
                        type="button"
                        class="w-full min-h-[48px] rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm active:bg-indigo-700"
                        @click="copyPublicLink"
                    >
                        Copiar link para participantes
                    </button>
                </div>
                <PixCard :pix-key="store.expense.pix_key" :pix-qr-code="store.expense.pix_qr_code || null" />
            </div>

            <!-- Modal editar despesa -->
            <Teleport to="body">
                <div
                    v-if="showEditModal"
                    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40"
                    role="dialog"
                    aria-modal="true"
                    @click.self="closeEditModal"
                >
                    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto p-4 space-y-4" @click.stop>
                        <h3 class="text-lg font-semibold text-gray-900">Editar despesa</h3>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Descricao</label>
                            <input
                                v-model="editDescription"
                                type="text"
                                class="w-full min-h-[44px] rounded-lg border px-3 text-sm"
                                :class="editFieldErrors.description ? 'border-red-400' : 'border-gray-300'"
                            />
                            <p v-if="editFieldErrors.description" class="text-xs text-red-600 mt-1">{{ editFieldErrors.description }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Valor total (R$)</label>
                            <input
                                :value="editAmount"
                                type="text"
                                inputmode="numeric"
                                class="w-full min-h-[44px] rounded-lg border px-3 text-sm"
                                :class="editFieldErrors.amount ? 'border-red-400' : 'border-gray-300'"
                                @input="onEditAmountInput"
                            />
                            <p v-if="editFieldErrors.amount" class="text-xs text-red-600 mt-1">{{ editFieldErrors.amount }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Vencimento (DD/MM/AAAA)</label>
                            <input
                                v-model="editDueDate"
                                type="text"
                                inputmode="numeric"
                                placeholder="DD/MM/AAAA"
                                class="w-full min-h-[44px] rounded-lg border px-3 text-sm"
                                :class="editFieldErrors.due_date ? 'border-red-400' : 'border-gray-300'"
                            />
                            <p v-if="editFieldErrors.due_date" class="text-xs text-red-600 mt-1">{{ editFieldErrors.due_date }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Chave PIX</label>
                            <input
                                v-model="editPixKey"
                                type="text"
                                class="w-full min-h-[44px] rounded-lg border px-3 text-sm"
                                :class="editFieldErrors.pix_key ? 'border-red-400' : 'border-gray-300'"
                            />
                            <p v-if="editFieldErrors.pix_key" class="text-xs text-red-600 mt-1">{{ editFieldErrors.pix_key }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">QR Code PIX (base64, opcional)</label>
                            <textarea
                                v-model="editPixQr"
                                rows="2"
                                placeholder="Opcional"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono"
                            />
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button
                                type="button"
                                class="flex-1 min-h-[48px] rounded-xl border border-gray-300 text-sm font-medium text-gray-700"
                                @click="closeEditModal"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="flex-1 min-h-[48px] rounded-xl bg-indigo-600 text-sm font-semibold text-white disabled:opacity-50"
                                :disabled="editSaving"
                                @click="saveExpenseEdit"
                            >
                                {{ editSaving ? 'Salvando...' : 'Salvar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <Card title="Participantes">
                <ul v-if="isParticipantMode" class="divide-y divide-gray-100">
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
                <MemberList
                    v-else
                    :members="store.expense.members || []"
                    :is-admin="!!store.expense.can_manage"
                    :show-resend="!!store.expense.can_manage && moderationEnabled"
                    :moderation-enabled="moderationEnabled"
                    @validate="validateCharge"
                    @reject="rejectCharge"
                    @view-proof="openProof"
                    @resend="resendMember"
                    @copy-participant-link="copyParticipantUrl"
                />
            </Card>

            <Modal
                :show="showCloseConfirmModal"
                title="Finalizar despesa"
                max-width="md"
                @close="showCloseConfirmModal = false"
            >
                <p class="text-sm text-gray-700">
                    Tem certeza que deseja finalizar esta despesa? Apos finalizar, nao sera possivel fazer alteracoes,
                    incluir participantes nem validar pagamentos.
                </p>
                <template #footer>
                    <Button variant="secondary" class="min-h-[48px]" @click="showCloseConfirmModal = false">
                        Cancelar
                    </Button>
                    <Button variant="success" class="min-h-[48px]" :loading="closeSubmitting" @click="confirmCloseExpense">
                        Confirmar
                    </Button>
                </template>
            </Modal>
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
