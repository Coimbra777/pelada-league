<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useExpenseStore } from '../../Stores/expenses.js';
import { useTeamStore } from '../../Stores/teams.js';
import { useToast } from '../../Composables/useToast.js';
import { api } from '../../Services/api.js';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/Card.vue';
import Input from '../../Components/Input.vue';
import Button from '../../Components/Button.vue';
import ParticipantsInput from '../../Components/ParticipantsInput.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({ teamId: [String, Number] });

const expenseStore = useExpenseStore();
const teamStore = useTeamStore();
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

const participants = ref([]);

async function submit() {
    errors.value = {};
    try {
        for (const member of participants.value) {
            try {
                await api.post(`/teams/${props.teamId}/members`, {
                    name: member.name,
                    phone: member.phone,
                });
            } catch {
                // Member may already exist, continue
            }
        }

        const expense = await expenseStore.createExpense(props.teamId, {
            description: form.description,
            total_amount: Number(form.total_amount),
            due_date: form.due_date,
            pix_key: form.pix_key,
            pix_qr_code: form.pix_qr_code || null,
        });
        toast.success('Despesa criada e dividida!');
        router.visit(`/teams/${props.teamId}/expenses/${expense.id}`);
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
        <Link :href="`/teams/${props.teamId}`" class="text-sm text-indigo-600 hover:text-indigo-800 mb-4 inline-block">
            &larr; Voltar
        </Link>

        <h1 class="text-2xl font-bold text-gray-900 mb-6">Criar Despesa</h1>

        <Card>
            <form @submit.prevent="submit" class="space-y-4">
                <Input v-model="form.description" label="Descricao" placeholder="Ex: Churrasco do sabado" :error="errors.description" required />

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

                <!--
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">QR Code PIX (opcional)</label>
                    <textarea
                        v-model="form.pix_qr_code"
                        rows="2"
                        placeholder="Cole o codigo base64 do QR Code"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                -->

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Participantes (opcional)</label>
                    <p class="text-xs text-gray-500 mb-2">
                        Novos contatos entram na equipe antes de dividir a despesa entre todos os membros.
                    </p>
                    <ParticipantsInput v-model="participants" :block-incomplete-rows="false" />
                </div>

                <p v-if="expenseStore.error" class="text-sm text-red-600">{{ expenseStore.error }}</p>
                <Button type="submit" :loading="expenseStore.loading" class="w-full" size="lg">Criar despesa</Button>
            </form>
        </Card>
    </div>
</template>
