<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { api } from '../../Services/api.js';
import { useToast } from '../../Composables/useToast.js';
import { useClipboard } from '../../Composables/useClipboard.js';
import { formatPhoneBr } from '../../Composables/useInputMasks.js';
import ParticipantLayout from '../../Layouts/ParticipantLayout.vue';
import Button from '../../Components/Button.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineOptions({ layout: ParticipantLayout });

const props = defineProps({
    hash: { type: String, required: true },
});

const toast = useToast();
const { copy } = useClipboard();

const loading = ref(true);
const loadError = ref(false);
const expense = ref(null);

const name = ref('');
const phone = ref('');
const file = ref(null);
const fileInput = ref(null);
const fieldErrors = ref({});

const validating = ref(false);
const submittingProof = ref(false);

/** Resposta 200 de validate-participant */
const validated = ref(null);

const isExpenseClosed = computed(() => expense.value?.status === 'closed');

function formatBrl(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

const qrSrc = computed(() => {
    const raw = expense.value?.pix_qr_code;
    if (!raw) return null;
    const s = String(raw).trim();
    if (s.startsWith('data:')) return s;
    return `data:image/png;base64,${s}`;
});

const phoneDigits = computed(() => phone.value.replace(/\D/g, ''));

const canValidate = computed(() => {
    return (
        name.value.trim().length > 0
        && phoneDigits.value.length >= 10
        && !validating.value
        && !isExpenseClosed.value
    );
});

const canSubmitProof = computed(() => {
    return (
        !!validated.value?.can_submit_proof
        && !!file.value
        && !submittingProof.value
        && !isExpenseClosed.value
    );
});

async function copyPixKey() {
    if (!expense.value?.pix_key) return;
    await copy(expense.value.pix_key);
    toast.success('Chave PIX copiada.');
}

onMounted(async () => {
    loading.value = true;
    loadError.value = false;
    try {
        const data = await api.get(`/public/expenses/${props.hash}`);
        expense.value = data.expense;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
});

function clearValidation() {
    validated.value = null;
    file.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function onNameInput() {
    clearValidation();
}

function onPhoneInput(ev) {
    phone.value = formatPhoneBr(ev.target.value);
    clearValidation();
}

function onFileChange(ev) {
    file.value = ev.target.files?.[0] ?? null;
    fieldErrors.value = {};
}

async function validateParticipant() {
    if (isExpenseClosed.value) return;
    fieldErrors.value = {};
    const trimmedName = name.value.trim();
    const digits = phoneDigits.value;

    if (!trimmedName) {
        fieldErrors.value.name = 'Informe seu nome.';
        return;
    }
    if (digits.length < 10) {
        fieldErrors.value.phone = 'Telefone invalido.';
        return;
    }

    validating.value = true;
    try {
        const data = await api.post(`/public/expenses/${props.hash}/validate-participant`, {
            name: trimmedName,
            phone: digits,
        });
        validated.value = {
            status: data.status,
            message: data.message,
            rejection_reason: data.rejection_reason ?? null,
            can_submit_proof: !!data.can_submit_proof,
        };
        toast.success('Dados confirmados.');
    } catch (err) {
        validated.value = null;
        const msg = err.data?.message || 'Nao foi possivel validar.';
        toast.error(msg);
    } finally {
        validating.value = false;
    }
}

async function submitProof() {
    if (isExpenseClosed.value || !validated.value?.can_submit_proof) return;

    fieldErrors.value = {};
    const trimmedName = name.value.trim();
    const digits = phoneDigits.value;

    if (!file.value) {
        fieldErrors.value.file = 'Selecione o comprovante.';
        return;
    }

    const maxSize = 5 * 1024 * 1024;
    if (file.value.size > maxSize) {
        toast.error('Arquivo muito grande. Maximo 5 MB.');
        return;
    }
    const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!allowed.includes(file.value.type)) {
        toast.error('Use JPG, PNG ou PDF.');
        return;
    }

    submittingProof.value = true;
    try {
        const formData = new FormData();
        formData.append('name', trimmedName);
        formData.append('phone', digits);
        formData.append('proof', file.value);

        const data = await api.postFormData(`/public/expenses/${props.hash}/submit-proof`, formData);
        validated.value = {
            status: data.status,
            message: data.message,
            rejection_reason: data.rejection_reason ?? null,
            can_submit_proof: false,
        };
        toast.success(data.message || 'Comprovante enviado.');
        file.value = null;
        if (fileInput.value) fileInput.value.value = '';
    } catch (err) {
        const d = err.data || {};
        if (d.status) {
            validated.value = {
                status: d.status,
                message: d.message,
                rejection_reason: d.rejection_reason ?? null,
                can_submit_proof: ['pending', 'rejected'].includes(d.status),
            };
        }
        toast.error(d.message || 'Nao foi possivel enviar.');
    } finally {
        submittingProof.value = false;
    }
}
</script>

<template>
    <Head title="Pagar via PIX" />

    <div v-if="loading" class="flex justify-center py-16">
        <div class="h-10 w-10 rounded-full border-2 border-indigo-600 border-t-transparent animate-spin" aria-hidden="true" />
    </div>

    <div v-else-if="loadError || !expense" class="text-center py-12 px-2">
        <p class="text-gray-700 text-base">Despesa nao encontrada.</p>
    </div>

    <div v-else class="space-y-8">
        <section class="space-y-4">
            <div class="text-center space-y-1">
                <p class="text-sm text-gray-600">Valor total</p>
                <p class="text-2xl font-bold tracking-tight">{{ formatBrl(expense.total_amount) }}</p>
                <p v-if="expense.amount_per_member != null" class="text-sm text-gray-600 pt-2">
                    Valor por pessoa:
                    <span class="font-semibold text-gray-900">{{ formatBrl(expense.amount_per_member) }}</span>
                </p>
            </div>

            <p class="text-center text-sm font-medium text-gray-800">Pagamento via PIX</p>

            <div v-if="qrSrc" class="flex justify-center">
                <img
                    :src="qrSrc"
                    alt="QR Code PIX"
                    class="w-52 h-52 rounded-xl border border-stone-200 bg-white object-contain"
                />
            </div>

            <div
                v-if="expense.pix_key"
                class="rounded-xl border border-stone-200 bg-white p-3 flex flex-col gap-2"
            >
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Chave PIX</p>
                <p class="text-sm font-mono text-gray-900 break-all">{{ expense.pix_key }}</p>
                <Button type="button" variant="secondary" class="w-full min-h-[48px]" @click="copyPixKey">
                    Copiar chave PIX
                </Button>
            </div>

            <p v-if="!expense.pix_qr_code && !expense.pix_key" class="text-sm text-center text-amber-800 bg-amber-50 rounded-lg px-3 py-2">
                Dados PIX nao configurados para esta despesa.
            </p>
        </section>

        <section
            v-if="isExpenseClosed"
            class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-6 text-center space-y-2"
        >
            <p class="text-lg font-semibold text-emerald-900">
                Esta despesa ja foi finalizada pelo responsavel.
            </p>
            <p class="text-sm text-emerald-800">
                Nao e possivel enviar comprovantes por aqui.
            </p>
        </section>

        <section v-else class="space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Participar</h2>
            <p class="text-xs text-gray-600">
                Informe <strong>exatamente</strong> o nome e o telefone cadastrados pelo responsavel (telefone pode ser digitado com mascara).
            </p>

            <div>
                <label for="pe-name" class="block text-sm font-medium text-gray-700 mb-1.5">Nome</label>
                <input
                    id="pe-name"
                    v-model="name"
                    type="text"
                    autocomplete="name"
                    class="w-full min-h-[48px] rounded-xl border border-stone-300 px-3 text-base shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    :class="fieldErrors.name ? 'border-red-400' : ''"
                    @input="onNameInput"
                />
                <p v-if="fieldErrors.name" class="text-sm text-red-600 mt-1">{{ fieldErrors.name }}</p>
            </div>

            <div>
                <label for="pe-phone" class="block text-sm font-medium text-gray-700 mb-1.5">Telefone</label>
                <input
                    id="pe-phone"
                    :value="phone"
                    type="tel"
                    inputmode="tel"
                    autocomplete="tel"
                    placeholder="(11) 99999-9999"
                    class="w-full min-h-[48px] rounded-xl border border-stone-300 px-3 text-base shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    :class="fieldErrors.phone ? 'border-red-400' : ''"
                    @input="onPhoneInput"
                />
                <p v-if="fieldErrors.phone" class="text-sm text-red-600 mt-1">{{ fieldErrors.phone }}</p>
            </div>

            <Button
                type="button"
                variant="secondary"
                class="w-full min-h-[48px] text-base font-semibold"
                :disabled="!canValidate"
                :loading="validating"
                @click="validateParticipant"
            >
                Validar meus dados
            </Button>

            <div
                v-if="validated"
                class="rounded-xl border border-stone-200 bg-stone-50 px-3 py-3 text-sm space-y-2"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-gray-600">Status:</span>
                    <StatusBadge :status="validated.status" />
                </div>
                <p class="text-gray-900 font-medium">{{ validated.message }}</p>
                <p v-if="validated.rejection_reason" class="text-xs text-orange-900 bg-orange-50 rounded-lg px-2 py-1.5">
                    Motivo da rejeicao: {{ validated.rejection_reason }}
                </p>
            </div>

            <template v-if="validated?.can_submit_proof">
                <div>
                    <label for="pe-file" class="block text-sm font-medium text-gray-700 mb-1.5">Comprovante</label>
                    <input
                        id="pe-file"
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/jpg,application/pdf"
                        class="block w-full min-h-[48px] text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-indigo-800"
                        :class="fieldErrors.file ? 'text-red-600' : ''"
                        @change="onFileChange"
                    />
                    <p class="text-xs text-gray-500 mt-1">JPG, PNG ou PDF. Ate 5 MB.</p>
                    <p v-if="fieldErrors.file" class="text-sm text-red-600 mt-1">{{ fieldErrors.file }}</p>
                </div>

                <Button
                    type="button"
                    class="w-full min-h-[52px] text-base font-semibold"
                    :disabled="!canSubmitProof"
                    :loading="submittingProof"
                    @click="submitProof"
                >
                    Enviar comprovante
                </Button>
            </template>
        </section>
    </div>
</template>
