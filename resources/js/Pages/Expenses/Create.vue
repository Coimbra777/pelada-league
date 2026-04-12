<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useExpenseStore } from '../../Stores/expenses.js';
import { useToast } from '../../Composables/useToast.js';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/Card.vue';
import Input from '../../Components/Input.vue';
import Button from '../../Components/Button.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({ teamId: [String, Number] });

const expenseStore = useExpenseStore();
const toast = useToast();

const form = reactive({
    description: '',
    total_amount: '',
    due_date: '',
    pix_key: '',
    pix_qr_code: '',
});
const errors = ref({});

const today = new Date().toISOString().split('T')[0];

async function submit() {
    errors.value = {};
    try {
        await expenseStore.createExpense(props.teamId, {
            description: form.description,
            total_amount: Number(form.total_amount),
            due_date: form.due_date,
            pix_key: form.pix_key,
            pix_qr_code: form.pix_qr_code || null,
        });
        toast.success('Despesa criada e dividida!');
        router.visit(`/teams/${props.teamId}`);
    } catch (err) {
        if (err.data?.errors) {
            errors.value = Object.fromEntries(
                Object.entries(err.data.errors).map(([k, v]) => [k, v[0]])
            );
        } else if (err.data?.message) {
            toast.error(err.data.message);
        }
    }
}
</script>

<template>
    <Head title="Nova Despesa" />
    <div class="max-w-lg mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Criar Despesa</h1>
        <Card>
            <form @submit.prevent="submit" class="space-y-4">
                <Input v-model="form.description" label="Descricao" placeholder="Ex: Jantar do time" :error="errors.description" required />
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor Total (R$) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                        <input
                            v-model="form.total_amount"
                            type="number"
                            step="0.01"
                            min="5"
                            required
                            placeholder="0,00"
                            :class="[
                                'block w-full rounded-lg border pl-10 pr-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0',
                                errors.total_amount
                                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                    : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
                            ]"
                        />
                    </div>
                    <p v-if="errors.total_amount" class="mt-1 text-sm text-red-600">{{ errors.total_amount }}</p>
                </div>
                <Input v-model="form.due_date" type="date" label="Vencimento" :min="today" :error="errors.due_date" required />
                <Input v-model="form.pix_key" label="Chave PIX" placeholder="CPF, telefone, email ou chave aleatoria" :error="errors.pix_key" required />
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">QR Code PIX (opcional)</label>
                    <textarea
                        v-model="form.pix_qr_code"
                        rows="3"
                        placeholder="Cole aqui o codigo do QR Code PIX (base64 ou copia-e-cola)"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="errors.pix_qr_code" class="mt-1 text-sm text-red-600">{{ errors.pix_qr_code }}</p>
                </div>
                <p v-if="expenseStore.error" class="text-sm text-red-600">{{ expenseStore.error }}</p>
                <Button type="submit" :loading="expenseStore.loading" class="w-full">Criar e Dividir</Button>
            </form>
        </Card>
    </div>
</template>
