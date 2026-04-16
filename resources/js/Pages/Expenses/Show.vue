<script setup>
import { ref, computed, onMounted, nextTick, reactive } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useExpenseStore } from '../../Stores/expenses.js';
import { useToast } from '../../Composables/useToast.js';
import { useClipboard } from '../../Composables/useClipboard.js';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/Card.vue';
import Button from '../../Components/Button.vue';
import Input from '../../Components/Input.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';
import Modal from '../../Components/Modal.vue';
import ExpenseHeader from '../../Components/ExpenseHeader.vue';
import PixCard from '../../Components/PixCard.vue';
import MemberList from '../../Components/MemberList.vue';
import ParticipantsInput from '../../Components/ParticipantsInput.vue';
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

const editModalOpen = ref(false);
const editSaving = ref(false);
const editErrors = ref({});
const editForm = reactive({
    description: '',
    total_amount: '',
    due_date: '',
    pix_key: '',
    pix_qr_code: '',
});
const newParticipants = ref([]);
const participantsInputRef = ref(null);

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

const allChargesPending = computed(() => {
    const charges = expense.value?.charges ?? [];
    if (!charges.length) {
        return true;
    }
    return charges.every((c) => c.status === 'pending');
});

/** Cobrancas fora de "pending" bloqueiam edicao estrutural (igual regras do backend). */
const expenseEditLocked = computed(() => !allChargesPending.value);

const existingParticipantPhones = computed(() => {
    const list = expense.value?.charges ?? [];
    return list
        .map((c) => String(c.member?.phone ?? '').replace(/\D/g, ''))
        .filter((p) => p.length >= 10);
});

/** Previsao (aprox.) do rateio se novos participantes forem salvos — mesmo arredondamento do backend no penultimo. */
const previewSplitAfterSave = computed(() => {
    const nNew = newParticipants.value.length;
    const nCur = expense.value?.charges?.length ?? 0;
    const total = Number(editForm.total_amount);
    if (nNew === 0 || !Number.isFinite(total) || nCur + nNew < 1) {
        return null;
    }
    const count = nCur + nNew;
    const base = Math.floor((total / count) * 100) / 100;
    const last = Math.round((total - base * (count - 1)) * 100) / 100;
    return { count, base, last, total };
});

function formatBrl(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return '—';
    }
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function openEditModal() {
    if (expenseEditLocked.value) {
        return;
    }
    const e = expense.value;
    if (!e) return;
    editForm.description = e.description;
    editForm.total_amount = e.total_amount;
    const rawDue = e.due_date;
    editForm.due_date = typeof rawDue === 'string' ? rawDue.split('T')[0] : '';
    editForm.pix_key = e.pix_key;
    editForm.pix_qr_code = e.pix_qr_code || '';
    editErrors.value = {};
    editModalOpen.value = true;
    nextTick(() => {
        participantsInputRef.value?.reset();
    });
}

async function saveExpenseEdit() {
    if (!participantsInputRef.value?.validate()) {
        return;
    }
    editErrors.value = {};
    editSaving.value = true;
    const addedCount = newParticipants.value.length;
    try {
        await expenseStore.updateExpense(props.teamId, props.id, {
            description: editForm.description,
            total_amount: Number(editForm.total_amount),
            due_date: editForm.due_date,
            pix_key: editForm.pix_key,
            pix_qr_code: editForm.pix_qr_code || null,
        });
        if (addedCount > 0) {
            await expenseStore.addExpenseParticipants(props.teamId, props.id, newParticipants.value);
        }
        await expenseStore.fetchExpense(props.teamId, props.id);
        const e = expenseStore.currentExpense;
        if (addedCount > 0) {
            toast.success(
                `${addedCount} participante(s) adicionado(s) com sucesso. Total ${formatBrl(e?.total_amount)} — ${formatBrl(e?.amount_per_member)} por pessoa.`,
            );
        } else {
            toast.success(
                `Despesa atualizada. Total ${formatBrl(e?.total_amount)} — ${formatBrl(e?.amount_per_member)} por pessoa.`,
            );
        }
        editModalOpen.value = false;
        nextTick(() => {
            participantsInputRef.value?.reset();
        });
    } catch (err) {
        if (err.data?.errors) {
            editErrors.value = Object.fromEntries(
                Object.entries(err.data.errors).map(([k, v]) => [k, v[0]]),
            );
        }
        toast.error(err.data?.message || 'Nao foi possivel salvar.');
    } finally {
        editSaving.value = false;
    }
}

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
                <div v-if="isAdmin" class="mt-4 space-y-3">
                    <div
                        v-if="expenseEditLocked"
                        class="rounded-xl border-2 border-amber-400 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm"
                        role="status"
                    >
                        <p class="font-semibold">Edicao bloqueada</p>
                        <p class="mt-1">
                            Esta despesa possui cobrancas em andamento (comprovante enviado, validacao ou outro status
                            diferente de pendente). Nao e possivel alterar o valor total nem incluir participantes.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="w-full min-h-[48px] rounded-xl border px-4 text-sm font-medium shadow-sm"
                        :class="
                            expenseEditLocked
                                ? 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-500'
                                : 'border-gray-300 bg-white text-gray-800 active:bg-gray-50'
                        "
                        :disabled="expenseEditLocked"
                        :title="expenseEditLocked ? 'Edicao bloqueada — ha cobrancas em andamento' : ''"
                        @click="openEditModal"
                    >
                        {{ expenseEditLocked ? 'Editar despesa (bloqueado)' : 'Editar despesa' }}
                    </button>
                </div>
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

        <Modal :show="editModalOpen" title="Editar despesa" max-width="lg" @close="editModalOpen = false">
            <form class="space-y-4" @submit.prevent="saveExpenseEdit">
                <Input
                    v-model="editForm.description"
                    label="Descricao"
                    :error="editErrors.description"
                    required
                />
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Valor total (R$) <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="editForm.total_amount"
                        type="number"
                        step="0.01"
                        min="5"
                        required
                        class="block w-full min-h-[48px] rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-0"
                    />
                    <p v-if="editErrors.total_amount" class="mt-1 text-sm text-red-600">{{ editErrors.total_amount }}</p>
                </div>
                <Input
                    v-model="editForm.due_date"
                    type="date"
                    label="Vencimento"
                    :error="editErrors.due_date"
                    required
                />
                <Input v-model="editForm.pix_key" label="Chave PIX" :error="editErrors.pix_key" required />
                <!--
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">QR Code PIX (opcional)</label>
                    <textarea
                        v-model="editForm.pix_qr_code"
                        rows="2"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                -->

                <div class="border-t border-gray-100 pt-4">
                    <h4 class="text-sm font-semibold text-gray-900 mb-2">Adicionar participantes</h4>
                    <p class="text-xs text-gray-500 mb-3">
                        Somente novos participantes. Quem ja esta na lista abaixo nao aparece aqui de novo.
                    </p>
                    <ParticipantsInput
                        ref="participantsInputRef"
                        v-model="newParticipants"
                        :existing-phones="existingParticipantPhones"
                    />
                    <div
                        v-if="previewSplitAfterSave"
                        class="mt-3 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-3 text-sm text-indigo-950"
                    >
                        <p class="font-semibold">Previsao ao salvar (rateio igual)</p>
                        <p class="mt-1">
                            Total {{ formatBrl(previewSplitAfterSave.total) }} dividido por
                            {{ previewSplitAfterSave.count }} participantes: cerca de
                            {{ formatBrl(previewSplitAfterSave.base) }} por pessoa (ultima parcela
                            {{ formatBrl(previewSplitAfterSave.last) }} para fechar centavos).
                        </p>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" class="min-h-[48px]" @click="editModalOpen = false">Cancelar</Button>
                <Button class="min-h-[48px]" :loading="editSaving" @click="saveExpenseEdit">Salvar</Button>
            </template>
        </Modal>
    </div>
</template>
