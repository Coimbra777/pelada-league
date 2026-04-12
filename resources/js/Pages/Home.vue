<script setup>
import { reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { api } from '../Services/api.js';
import { saveExpense } from '../Services/localExpenses.js';
import { useToast } from '../Composables/useToast.js';
import { useDateBr } from '../Composables/useDateBr.js';
import { formatPhoneBr, maskCurrencyFromDigits, parseCurrencyBrToNumber } from '../Composables/useInputMasks.js';
import PublicLayout from '../Layouts/PublicLayout.vue';
import Card from '../Components/Card.vue';
import Input from '../Components/Input.vue';
import Button from '../Components/Button.vue';
import ParticipantsInput from '../Components/ParticipantsInput.vue';

defineOptions({ layout: PublicLayout });

const toast = useToast();
const { formatDateIsoToBr } = useDateBr();

const form = reactive({
    owner_name: '',
    owner_phone: '',
    description: '',
    amount: '',
    pix_key: '',
    pix_qr_code: '',
    due_date: '',
});

const participants = ref([]);
const errors = ref({});
const loading = ref(false);

const today = new Date().toISOString().split('T')[0];

async function submit() {
    errors.value = {};
    const phoneDigits = form.owner_phone.replace(/\D/g, '');
    if (phoneDigits.length < 10) {
        errors.value.owner_phone = 'Informe um telefone valido (min. 10 digitos).';
        return;
    }
    const amountNum = parseCurrencyBrToNumber(form.amount);
    if (!Number.isFinite(amountNum) || amountNum < 1) {
        errors.value.amount = 'Informe um valor minimo de R$ 1,00.';
        return;
    }

    loading.value = true;
    const payload = {
        owner_name: form.owner_name.trim(),
        owner_phone: form.owner_phone.replace(/\D/g, ''),
        description: form.description.trim(),
        amount: amountNum,
        pix_key: form.pix_key.trim(),
        pix_qr_code: form.pix_qr_code.trim() || null,
        due_date: form.due_date,
        participants: participants.value,
    };

    try {
        const data = await api.createPublicExpense(payload);
        saveExpense({
            hash: data.expense.public_hash,
            manage_token: data.expense.manage_token,
            description: payload.description,
            amount: payload.amount,
            created_at: new Date().toISOString(),
        });
        toast.success('Despesa criada!');
        router.visit(data.expense.manage_path);
    } catch (err) {
        if (err.data?.errors) {
            errors.value = Object.fromEntries(
                Object.entries(err.data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]),
            );
        } else {
            toast.error(err.data?.message || 'Nao foi possivel criar.');
        }
    } finally {
        loading.value = false;
    }
}

function onPhoneInput(e) {
    form.owner_phone = formatPhoneBr(e.target.value);
}

function onAmountInput(e) {
    form.amount = maskCurrencyFromDigits(e.target.value);
}
</script>

<template>
    <Head title="Caixinha" />
    <div class="max-w-lg mx-auto pb-10">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Nova despesa</h1>
        <p class="text-sm text-gray-500 mb-6">
            Crie sem login. Guarde o link de gestao (com <code class="text-xs bg-gray-100 px-1 rounded">?manage=</code>) para validar pagamentos.
        </p>

        <Card>
            <form class="space-y-4" @submit.prevent="submit">
                <Input v-model="form.owner_name" label="Seu nome (responsavel)" :error="errors.owner_name" required />
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Seu telefone <span class="text-red-500">*</span>
                    </label>
                    <input
                        :value="form.owner_phone"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        placeholder="(11) 99999-9999"
                        required
                        :class="[
                            'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-0 text-gray-900',
                            errors.owner_phone
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
                        ]"
                        @input="onPhoneInput"
                    />
                    <p v-if="errors.owner_phone" class="mt-1 text-sm text-red-600">{{ errors.owner_phone }}</p>
                </div>
                <Input v-model="form.description" label="Descricao" :error="errors.description" required />
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor total (R$) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500">R$</span>
                        <input
                            :value="form.amount"
                            type="text"
                            inputmode="numeric"
                            placeholder="0,00"
                            required
                            :class="[
                                'block w-full rounded-lg border py-2 pl-10 pr-3 text-sm shadow-sm focus:outline-none focus:ring-2',
                                errors.amount
                                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                    : 'border-gray-300 focus:ring-indigo-500 focus:border-indigo-500',
                            ]"
                            @input="onAmountInput"
                        />
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Digite apenas numeros; centavos sao aplicados automaticamente.</p>
                    <p v-if="errors.amount" class="mt-1 text-sm text-red-600">{{ errors.amount }}</p>
                </div>
                <Input v-model="form.pix_key" label="Chave PIX" :error="errors.pix_key" required />
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">QR Code PIX (opcional)</label>
                    <textarea
                        v-model="form.pix_qr_code"
                        rows="2"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <Input v-model="form.due_date" type="date" label="Vencimento" :min="today" :error="errors.due_date" required />
                <p class="text-xs text-gray-500">Exibicao: {{ formatDateIsoToBr(form.due_date || null) }}</p>

                <ParticipantsInput v-model="participants" :block-incomplete-rows="false" />
                <p v-if="errors.participants" class="text-sm text-red-600">{{ errors.participants }}</p>

                <Button type="submit" class="w-full min-h-[52px]" size="lg" :loading="loading">
                    Criar despesa
                </Button>
            </form>
        </Card>
    </div>
</template>
