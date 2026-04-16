<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useAuthStore } from '../../Stores/auth.js';
import { useToast } from '../../Composables/useToast.js';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import Card from '../../Components/Card.vue';
import Input from '../../Components/Input.vue';
import Button from '../../Components/Button.vue';

defineOptions({ layout: GuestLayout });

const authStore = useAuthStore();
const toast = useToast();

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    phone: '',
    cpf: '',
});
const errors = ref({});

async function submit() {
    errors.value = {};
    try {
        await authStore.register({
            ...form,
            phone: form.phone.replace(/\D/g, '') || null,
        });
        toast.success('Conta criada com sucesso!');
        router.visit('/dashboard');
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
    <Head title="Criar Conta" />
    <Card>
        <form @submit.prevent="submit" class="space-y-4">
            <Input v-model="form.name" label="Nome" placeholder="Seu nome" :error="errors.name" required />
            <Input v-model="form.email" type="email" label="E-mail" placeholder="seu@email.com" :error="errors.email" required />
            <Input v-model="form.password" type="password" label="Senha" placeholder="Minimo 6 caracteres" :error="errors.password" required />
            <Input v-model="form.password_confirmation" type="password" label="Confirmar Senha" placeholder="Repita a senha" required />
            <Input v-model="form.phone" phone-mask label="Telefone" placeholder="(11) 99999-9999 (opcional)" :error="errors.phone" />
            <Input v-model="form.cpf" label="CPF" placeholder="11 digitos, sem pontos (opcional)" :error="errors.cpf" />
            <p v-if="authStore.error && typeof authStore.error === 'string'" class="text-sm text-red-600">{{ authStore.error }}</p>
            <Button type="submit" :loading="authStore.loading" class="w-full">Criar Conta</Button>
        </form>
        <template #footer>
            <p class="text-sm text-center text-gray-500 w-full">
                Ja tem conta?
                <Link href="/login" class="text-indigo-600 hover:text-indigo-800 font-medium">Entrar</Link>
            </p>
        </template>
    </Card>
</template>
