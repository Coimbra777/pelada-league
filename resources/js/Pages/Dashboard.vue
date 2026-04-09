<script setup>
import { onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useAuthStore } from '../Stores/auth.js';
import { useTeamStore } from '../Stores/teams.js';
import AppLayout from '../Layouts/AppLayout.vue';
import Card from '../Components/Card.vue';
import Button from '../Components/Button.vue';
import LoadingSpinner from '../Components/LoadingSpinner.vue';

defineOptions({ layout: AppLayout });

const authStore = useAuthStore();
const teamStore = useTeamStore();

onMounted(() => {
    teamStore.fetchTeams();
});
</script>

<template>
    <Head title="Dashboard" />
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-gray-500 text-sm mt-1">Ola, {{ authStore.userName }}!</p>
            </div>
            <Link href="/teams/create">
                <Button>Nova Equipe</Button>
            </Link>
        </div>

        <LoadingSpinner v-if="teamStore.loading" />

        <div v-else-if="teamStore.teams.length === 0" class="text-center py-16">
            <div class="text-gray-400 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">Nenhuma equipe ainda</h3>
            <p class="text-gray-500 mb-4">Crie sua primeira equipe para comecar a dividir despesas.</p>
            <Link href="/teams/create">
                <Button>Criar Equipe</Button>
            </Link>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link v-for="team in teamStore.teams" :key="team.id" :href="`/teams/${team.id}`" class="block">
                <Card class="hover:shadow-md transition-shadow cursor-pointer">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ team.name }}</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ team.members_count }} membro(s)</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Card>
            </Link>
        </div>
    </div>
</template>
