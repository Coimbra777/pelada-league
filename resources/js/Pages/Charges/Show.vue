<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useExpenseStore } from '../../Stores/expenses.js';
import { useClipboard } from '../../Composables/useClipboard.js';
import { useToast } from '../../Composables/useToast.js';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/Card.vue';
import Button from '../../Components/Button.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({ id: [String, Number] });

const expenseStore = useExpenseStore();
const { copy } = useClipboard();
const toast = useToast();

const charge = computed(() => expenseStore.getChargeById(props.id));

const qrCodeSrc = computed(() => {
    if (!charge.value?.pix_qr_code) return null;
    const data = charge.value.pix_qr_code;
    return data.startsWith('data:') ? data : `data:image/png;base64,${data}`;
});

async function syncStatus() {
    try {
        await expenseStore.syncCharge(Number(props.id));
        toast.success('Status atualizado!');
    } catch {
        toast.error('Falha ao sincronizar.');
    }
}

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
</script>

<template>
    <Head title="Cobranca PIX" />
    <div class="max-w-lg mx-auto">
        <Link href="/dashboard" class="text-sm text-indigo-600 hover:text-indigo-800 mb-4 inline-block">
            &larr; Voltar
        </Link>

        <template v-if="charge">
            <Card class="mb-6">
                <div class="text-center mb-4">
                    <h1 class="text-xl font-bold text-gray-900 mb-1">{{ charge.description }}</h1>
                    <p class="text-3xl font-bold text-indigo-600">{{ formatCurrency(charge.amount) }}</p>
                    <div class="mt-2">
                        <StatusBadge :status="charge.status" />
                    </div>
                </div>

                <!-- QR Code -->
                <div v-if="qrCodeSrc" class="flex justify-center mb-6">
                    <img :src="qrCodeSrc" alt="QR Code PIX" class="w-56 h-56 rounded-lg border" />
                </div>

                <!-- Copy-paste code -->
                <div v-if="charge.pix_copy_paste" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Codigo PIX (copia e cola)</label>
                    <div class="relative">
                        <textarea
                            :value="charge.pix_copy_paste"
                            readonly
                            rows="3"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-700 bg-gray-50 resize-none"
                        />
                    </div>
                    <Button @click="copy(charge.pix_copy_paste)" class="w-full mt-2" variant="secondary">
                        Copiar Codigo PIX
                    </Button>
                </div>

                <!-- Payment link -->
                <a
                    v-if="charge.payment_link"
                    :href="charge.payment_link"
                    target="_blank"
                    rel="noopener"
                    class="block text-center text-sm text-indigo-600 hover:text-indigo-800 mb-4"
                >
                    Abrir Link de Pagamento &rarr;
                </a>

                <Button @click="syncStatus" variant="secondary" class="w-full">
                    Sincronizar Status
                </Button>
            </Card>

            <div class="text-center text-xs text-gray-400">
                <p v-if="charge.paid_at">Pago em: {{ charge.paid_at }}</p>
                <p>Vencimento: {{ charge.due_date }}</p>
            </div>
        </template>

        <template v-else>
            <Card>
                <div class="text-center py-8">
                    <p class="text-gray-500 mb-4">Cobranca nao encontrada. Acesse pela pagina de despesa.</p>
                    <Link href="/dashboard">
                        <Button variant="secondary">Ir para Dashboard</Button>
                    </Link>
                </div>
            </Card>
        </template>
    </div>
</template>
