<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { api } from '../Services/api.js';
import { getExpenses, removeExpense } from '../Services/localExpenses.js';
import { useDateBr } from '../Composables/useDateBr.js';
import PublicLayout from '../Layouts/PublicLayout.vue';
import Card from '../Components/Card.vue';
import Button from '../Components/Button.vue';
import LoadingSpinner from '../Components/LoadingSpinner.vue';

defineOptions({ layout: PublicLayout });

const { formatDateIsoToBr } = useDateBr();

const rows = ref([]);
const loading = ref(true);

const sorted = computed(() =>
    [...rows.value].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)),
);

function formatCurrency(v) {
    return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDateShort(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

async function enrichItem(item) {
    try {
        const data = await api.get(`/public/expenses/${item.hash}`);
        const members = data.expense?.members ?? [];
        const total = members.length;
        const paid = members.filter((m) => m.charge_status === 'validated').length;
        return {
            ...item,
            paid,
            total,
            fetchError: false,
        };
    } catch {
        return { ...item, paid: null, total: null, fetchError: true };
    }
}

onMounted(async () => {
    loading.value = true;
    const list = getExpenses();
    rows.value = await Promise.all(list.map((item) => enrichItem(item)));
    loading.value = false;
});

function openExpense(item) {
    router.visit(`/public/expenses/${item.hash}?manage=${encodeURIComponent(item.manage_token)}`);
}

function removeLocal(item) {
    removeExpense(item.hash);
    rows.value = rows.value.filter((r) => r.hash !== item.hash);
}
</script>

<template>
    <Head title="Minhas despesas" />
    <div class="max-w-lg mx-auto pb-10">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Minhas despesas</h1>
        <p class="text-sm text-gray-500 mb-6">
            Lista salva neste aparelho. Para outro navegador ou telefone, use o link de administrador que voce guardou.
        </p>

        <LoadingSpinner v-if="loading" />

        <div v-else-if="sorted.length === 0" class="text-center py-12 text-gray-500 rounded-xl border border-dashed border-gray-300 bg-white px-4">
            Nenhuma despesa criada ainda
        </div>

        <div v-else class="space-y-3">
            <Card v-for="item in sorted" :key="item.hash">
                <div class="flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 truncate">{{ item.description }}</p>
                            <p class="text-sm text-gray-500 mt-0.5">{{ formatCurrency(item.amount) }}</p>
                            <p class="text-xs text-gray-400 mt-1">Criada em {{ formatDateShort(item.created_at) }}</p>
                        </div>
                        <Button size="sm" type="button" @click="openExpense(item)">
                            Abrir
                        </Button>
                    </div>
                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-gray-100">
                        <p v-if="!item.fetchError && item.total != null" class="text-xs text-gray-600">
                            Pagos: <span class="font-semibold text-gray-800">{{ item.paid }}/{{ item.total }}</span>
                        </p>
                        <p v-else-if="item.fetchError" class="text-xs text-amber-700">Nao foi possivel atualizar status</p>
                        <button
                            type="button"
                            class="text-xs text-red-600 hover:text-red-800 font-medium"
                            @click="removeLocal(item)"
                        >
                            Remover da lista
                        </button>
                    </div>
                </div>
            </Card>
        </div>

        <Link href="/" class="mt-8 inline-block text-sm text-indigo-600 hover:text-indigo-800">
            &larr; Voltar ao inicio
        </Link>
    </div>
</template>
