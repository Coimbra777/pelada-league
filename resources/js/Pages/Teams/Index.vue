<script setup>
import { onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useTeamStore } from '../../Stores/teams.js';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/Card.vue';
import Button from '../../Components/Button.vue';
import LoadingSpinner from '../../Components/LoadingSpinner.vue';

defineOptions({ layout: AppLayout });

const teamStore = useTeamStore();

onMounted(() => {
    teamStore.fetchTeams();
});
</script>

<template>
    <Head title="Equipes" />
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Minhas Equipes</h1>
            <Link href="/teams/create">
                <Button>Nova Equipe</Button>
            </Link>
        </div>

        <LoadingSpinner v-if="teamStore.loading" />

        <div v-else-if="teamStore.teams.length === 0" class="text-center py-12">
            <p class="text-gray-500">Voce ainda nao faz parte de nenhuma equipe.</p>
        </div>

        <div v-else class="space-y-3">
            <Link v-for="team in teamStore.teams" :key="team.id" :href="`/teams/${team.id}`" class="block">
                <Card class="hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ team.name }}</h3>
                            <p class="text-sm text-gray-500">{{ team.members_count }} membro(s)</p>
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
