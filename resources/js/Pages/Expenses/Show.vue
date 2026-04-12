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

const existingParticipantPhones = computed(() => {
    const list = expense.value?.charges ?? [];
    return list
        .map((c) => String(c.member?.phone ?? '').replace(/\D/g, ''))
        .filter((p) => p.length >= 10);
});

function openEditModal() {
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
    try {
        await expenseStore.updateExpense(props.teamId, props.id, {
            description: editForm.description,
            total_amount: Number(editForm.total_amount),
            due_date: editForm.due_date,
            pix_key: editForm.pix_key,
            pix_qr_code: editForm.pix_qr_code || null,
        });
        if (newParticipants.value.length > 0) {
            await expenseStore.addExpenseParticipants(props.teamId, props.id, newParticipants.value);
        }
        await expenseStore.fetchExpense(props.teamId, props.id);
        toast.success('Despesa atualizada!');
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
                <button
                    v-if="isAdmin"
                    type="button"
                    class="mt-4 w-full min-h-[48px] rounded-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-800 shadow-sm active:bg-gray-50"
                    @click="openEditModal"
                >
                    Editar despesa
                </button>
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
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">QR Code PIX (opcional)</label>
                    <textarea
                        v-model="editForm.pix_qr_code"
                        rows="2"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

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
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" class="min-h-[48px]" @click="editModalOpen = false">Cancelar</Button>
                <Button class="min-h-[48px]" :loading="editSaving" @click="saveExpenseEdit">Salvar</Button>
            </template>
        </Modal>
    </div>
</template>
