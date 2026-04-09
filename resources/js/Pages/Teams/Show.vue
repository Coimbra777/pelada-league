<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useAuthStore } from '../../Stores/auth.js';
import { useTeamStore } from '../../Stores/teams.js';
import { useExpenseStore } from '../../Stores/expenses.js';
import { useToast } from '../../Composables/useToast.js';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/Card.vue';
import Button from '../../Components/Button.vue';
import Input from '../../Components/Input.vue';
import Modal from '../../Components/Modal.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({ id: [String, Number] });

const authStore = useAuthStore();
const teamStore = useTeamStore();
const expenseStore = useExpenseStore();
const toast = useToast();

const showAddMember = ref(false);
const newMemberId = ref('');

const isAdmin = computed(() => {
    return teamStore.members.find(m => m.id === authStore.user?.id)?.role === 'admin';
});

onMounted(() => {
    teamStore.fetchTeam(props.id);
    teamStore.fetchDashboard(props.id);
    expenseStore.fetchExpenses(props.id);
});

async function addMember() {
    try {
        await teamStore.addMember(props.id, Number(newMemberId.value));
        toast.success('Membro adicionado!');
        showAddMember.value = false;
        newMemberId.value = '';
        teamStore.fetchDashboard(props.id);
    } catch {
        // error handled by store
    }
}

async function removeMember(userId) {
    if (!confirm('Remover este membro?')) return;
    try {
        await teamStore.removeMember(props.id, userId);
        toast.success('Membro removido.');
        teamStore.fetchDashboard(props.id);
    } catch {
        // error handled by store
    }
}

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
</script>

<template>
    <Head :title="teamStore.currentTeam?.name || 'Equipe'" />
    <div>
        <LoadingSpinner v-if="teamStore.loading && !teamStore.currentTeam" />

        <template v-else-if="teamStore.currentTeam">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">{{ teamStore.currentTeam.name }}</h1>
            </div>

            <!-- Dashboard Stats -->
            <div v-if="teamStore.dashboard" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <Card>
                    <p class="text-sm text-gray-500">Despesas</p>
                    <p class="text-2xl font-bold text-gray-900">{{ teamStore.dashboard.total_expenses }}</p>
                </Card>
                <Card>
                    <p class="text-sm text-gray-500">Em aberto</p>
                    <p class="text-2xl font-bold text-yellow-600">{{ formatCurrency(teamStore.dashboard.total_open) }}</p>
                </Card>
                <Card>
                    <p class="text-sm text-gray-500">Pago</p>
                    <p class="text-2xl font-bold text-green-600">{{ formatCurrency(teamStore.dashboard.total_paid) }}</p>
                </Card>
                <Card>
                    <p class="text-sm text-gray-500">Pendentes</p>
                    <p class="text-2xl font-bold text-gray-900">{{ teamStore.dashboard.members_pending }}</p>
                </Card>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Members -->
                <div>
                    <Card>
                        <template #header>
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-semibold text-gray-900">Membros</h3>
                                <button
                                    v-if="isAdmin"
                                    @click="showAddMember = true"
                                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                                >
                                    + Adicionar
                                </button>
                            </div>
                        </template>
                        <div class="space-y-3">
                            <div
                                v-for="member in teamStore.members"
                                :key="member.id"
                                class="flex items-center justify-between"
                            >
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ member.user.name }}</p>
                                    <p class="text-xs text-gray-500">{{ member.user.email }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span :class="[
                                        'text-xs px-2 py-0.5 rounded-full',
                                        member.role === 'admin' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'
                                    ]">
                                        {{ member.role }}
                                    </span>
                                    <button
                                        v-if="isAdmin && member.id !== teamStore.currentTeam.owner?.id"
                                        @click="removeMember(member.id)"
                                        class="text-xs text-red-500 hover:text-red-700"
                                    >
                                        Remover
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                <!-- Expenses -->
                <div class="lg:col-span-2">
                    <Card>
                        <template #header>
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-semibold text-gray-900">Despesas</h3>
                                <Link v-if="isAdmin" :href="`/teams/${props.id}/expenses/create`">
                                    <Button size="sm">Nova Despesa</Button>
                                </Link>
                            </div>
                        </template>
                        <LoadingSpinner v-if="expenseStore.loading" size="sm" />
                        <div v-else-if="expenseStore.expenses.length === 0" class="text-center py-6 text-gray-500 text-sm">
                            Nenhuma despesa criada.
                        </div>
                        <div v-else class="divide-y divide-gray-100">
                            <Link
                                v-for="expense in expenseStore.expenses"
                                :key="expense.id"
                                :href="`/teams/${props.id}/expenses/${expense.id}`"
                                class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-4 sm:-mx-6 px-4 sm:px-6 transition-colors"
                            >
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ expense.description }}</p>
                                    <p class="text-xs text-gray-500">{{ formatCurrency(expense.total_amount) }}</p>
                                </div>
                                <StatusBadge :status="expense.status" />
                            </Link>
                        </div>
                    </Card>
                </div>
            </div>

            <!-- Add Member Modal -->
            <Modal :show="showAddMember" title="Adicionar Membro" @close="showAddMember = false">
                <form @submit.prevent="addMember" class="space-y-4">
                    <Input v-model="newMemberId" label="ID do usuario" placeholder="Digite o ID do usuario" required />
                    <p v-if="teamStore.error" class="text-sm text-red-600">{{ teamStore.error }}</p>
                </form>
                <template #footer>
                    <Button variant="secondary" @click="showAddMember = false">Cancelar</Button>
                    <Button @click="addMember" :loading="teamStore.loading">Adicionar</Button>
                </template>
            </Modal>
        </template>
    </div>
</template>
