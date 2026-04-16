<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useTeamStore } from '../../Stores/teams.js';
import { useToast } from '../../Composables/useToast.js';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/Card.vue';
import Input from '../../Components/Input.vue';
import Button from '../../Components/Button.vue';

defineOptions({ layout: AppLayout });

const teamStore = useTeamStore();
const toast = useToast();

const name = ref('');
const errors = ref({});

async function submit() {
    errors.value = {};
    try {
        const team = await teamStore.createTeam(name.value);
        toast.success('Equipe criada!');
        router.visit(`/teams/${team.id}`);
    } catch (err) {
        if (err.data?.errors) {
            errors.value = Object.fromEntries(
                Object.entries(err.data.errors).map(([k, v]) => [k, v[0]])
            );
        }
    }
}
</script>

<template>
    <Head title="Nova Equipe" />
    <div class="max-w-lg mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Criar Equipe</h1>
        <Card>
            <form @submit.prevent="submit" class="space-y-4">
                <Input v-model="name" label="Nome da equipe" placeholder="Ex: Republica, Viagem SP..." :error="errors.name" required />
                <Button type="submit" :loading="teamStore.loading" class="w-full">Criar</Button>
            </form>
        </Card>
    </div>
</template>
