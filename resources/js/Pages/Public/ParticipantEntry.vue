<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { api } from '../../Services/api.js';
import { useToast } from '../../Composables/useToast.js';
import { useClipboard } from '../../Composables/useClipboard.js';
import { formatPhoneBr } from '../../Composables/useInputMasks.js';
import ParticipantLayout from '../../Layouts/ParticipantLayout.vue';
import Button from '../../Components/Button.vue';

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
const submitting = ref(false);
const success = ref(false);

const LS_KEY = computed(() => `pure_participant_${props.hash}`);

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

const canSubmit = computed(() => {
    const digits = phone.value.replace(/\D/g, '');
    return (
        name.value.trim().length > 0
        && digits.length >= 10
        && !!file.value
        && !submitting.value && !success.value
    );
});

function onPhoneInput(ev) {
    phone.value = formatPhoneBr(ev.target.value);
}

function onFileChange(ev) {
    file.value = ev.target.files?.[0] ?? null;
    fieldErrors.value = {};
}

async function copyPixKey() {
    if (!expense.value?.pix_key) return;
    await copy(expense.value.pix_key);
    toast.success('Chave PIX copiada.');
}

async function restoreFromStorage() {
    const stored = localStorage.getItem(LS_KEY.value);
    if (!stored) return;

    try {
        const bundle = await api.get(`/public/expenses/${props.hash}/participants/${stored}`);
        const st = bundle.charge?.status;
        if (st === 'proof_sent' || st === 'validated') {
            success.value = true;
        }
    } catch {
        localStorage.removeItem(LS_KEY.value);
    }
}

onMounted(async () => {
    loading.value = true;
    loadError.value = false;
    try {
        const data = await api.get(`/public/expenses/${props.hash}`);
        expense.value = data.expense;
        await restoreFromStorage();
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
});

async function submit() {
    fieldErrors.value = {};
    const trimmedName = name.value.trim();
    const phoneDigits = phone.value.replace(/\D/g, '');

    if (!trimmedName) {
        fieldErrors.value.name = 'Informe seu nome.';
        return;
    }
    if (phoneDigits.length < 10) {
        fieldErrors.value.phone = 'Telefone invalido.';
        return;
    }
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

    submitting.value = true;
    try {
        const idRes = await api.post(`/public/expenses/${props.hash}/identify`, {
            name: trimmedName,
            phone: phoneDigits,
        });

        const members = idRes.members || [];
        if (members.length === 0) {
            toast.error('Nao foi possivel identificar participante.');
            return;
        }

        const member = members[0];
        const st = member.status;

        if (st === 'proof_sent' || st === 'validated') {
            if (member.unique_hash) {
                localStorage.setItem(LS_KEY.value, member.unique_hash);
            }
            success.value = true;
            toast.success('Seu pagamento ja esta registrado.');
            return;
        }

        await api.upload(`/public/charges/${member.charge_id}/upload-proof`, file.value, 'file');
        await api.post(`/public/charges/${member.charge_id}/mark-as-paid`, {});

        if (member.unique_hash) {
            localStorage.setItem(LS_KEY.value, member.unique_hash);
        }

        success.value = true;
        file.value = null;
        if (fileInput.value) fileInput.value.value = '';
        toast.success('Comprovante enviado!');
    } catch (err) {
        let msg = err.data?.message;
        if (!msg && err.data?.errors) {
            msg = Object.values(err.data.errors).flat().join(' ');
        }
        toast.error(msg || 'Nao foi possivel enviar. Tente novamente.');
    } finally {
        submitting.value = false;
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
        <!-- Bloco 1: despesa + PIX -->
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

        <!-- Bloco 3: sucesso -->
        <section
            v-if="success"
            class="rounded-2xl border border-green-200 bg-green-50 px-4 py-6 text-center space-y-2"
        >
            <p class="text-lg font-semibold text-green-900">
                Comprovante enviado com sucesso!
            </p>
            <p class="text-sm text-green-800">
                Aguardando validacao do responsavel.
            </p>
        </section>

        <!-- Bloco 2: formulario -->
        <section v-else class="space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Seus dados</h2>

            <div>
                <label for="pe-name" class="block text-sm font-medium text-gray-700 mb-1.5">Nome</label>
                <input
                    id="pe-name"
                    v-model="name"
                    type="text"
                    autocomplete="name"
                    class="w-full min-h-[48px] rounded-xl border border-stone-300 px-3 text-base shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    :class="fieldErrors.name ? 'border-red-400' : ''"
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
                :disabled="!canSubmit"
                :loading="submitting"
                @click="submit"
            >
                Enviar comprovante e marcar como pago
            </Button>
            <p v-if="!canSubmit && !submitting && !success" class="text-xs text-center text-gray-500">
                Preencha nome, telefone e anexe o comprovante para habilitar o envio.
            </p>
        </section>
    </div>
</template>
