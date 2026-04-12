<script setup>
import { onMounted, computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useAuthStore } from '../Stores/auth.js';
import { useTeamStore } from '../Stores/teams.js';
import { useExpenseStore } from '../Stores/expenses.js';
import { useToast } from '../Composables/useToast.js';
import AppLayout from '../Layouts/AppLayout.vue';
import Card from '../Components/Card.vue';
import Button from '../Components/Button.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import LoadingSpinner from '../Components/LoadingSpinner.vue';

defineOptions({ layout: AppLayout });

const authStore = useAuthStore();
const teamStore = useTeamStore();
const expenseStore = useExpenseStore();
const toast = useToast();

const firstTeam = computed(() => teamStore.teams[0] ?? null);
const setupFailed = ref(false);
const bootstrapping = ref(true);

async function bootstrap() {
    setupFailed.value = false;
    await teamStore.fetchTeams();
    if (!teamStore.teams.length) {
        try {
            await teamStore.createTeam('Meu grupo');
            await teamStore.fetchTeams();
        } catch {
            setupFailed.value = true;
            toast.error('Nao foi possivel criar seu grupo. Tente novamente.');
            return;
        }
    }
    if (firstTeam.value) {
        await expenseStore.fetchExpenses(firstTeam.value.id);
    }
}

onMounted(async () => {
    await bootstrap();
    bootstrapping.value = false;
});

async function retrySetup() {
    bootstrapping.value = true;
    await bootstrap();
    bootstrapping.value = false;
}

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
</script>

<template>
    <Head title="Inicio" />
    <div class="max-w-lg mx-auto">
        <p class="text-gray-500 text-sm mb-6">Ola, {{ authStore.userName }}!</p>

        <LoadingSpinner v-if="bootstrapping || teamStore.loading" />

        <div v-else-if="setupFailed || !firstTeam" class="text-center py-10 space-y-4">
            <p class="text-gray-600 text-sm">Nao foi possivel preparar seu grupo.</p>
            <Button class="w-full min-h-[48px]" size="lg" @click="retrySetup">Tentar de novo</Button>
        </div>

        <template v-else>
            <Link :href="`/teams/${firstTeam.id}/expenses/create`">
                <Button size="lg" class="w-full min-h-[52px] mb-6">+ Criar despesa</Button>
            </Link>

            <div v-if="expenseStore.expenses.length > 0">
                <h3 class="text-sm font-medium text-gray-500 mb-3">Despesas recentes</h3>
                <div class="space-y-3">
                    <Link
                        v-for="expense in expenseStore.expenses.slice(0, 10)"
                        :key="expense.id"
                        :href="`/teams/${firstTeam.id}/expenses/${expense.id}`"
                        class="block"
                    >
                        <Card class="hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 truncate">{{ expense.description }}</p>
                                    <p class="text-sm text-gray-500 mt-0.5">{{ formatCurrency(expense.total_amount) }}</p>
                                </div>
                                <StatusBadge :status="expense.status" />
                            </div>
                        </Card>
                    </Link>
                </div>
            </div>
        </template>
    </div>
</template>

