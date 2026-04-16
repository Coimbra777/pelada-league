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

const form = reactive({ email: '', password: '' });
const errors = ref({});

async function submit() {
    errors.value = {};
    try {
        await authStore.login(form.email, form.password);
        toast.success('Login realizado!');
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
    <Head title="Login" />
    <Card>
        <form @submit.prevent="submit" class="space-y-4">
            <Input
                v-model="form.email"
                type="email"
                label="E-mail"
                placeholder="seu@email.com"
                :error="errors.email"
                required
            />
            <Input
                v-model="form.password"
                type="password"
                label="Senha"
                placeholder="Sua senha"
                :error="errors.password"
                required
            />
            <p v-if="authStore.error" class="text-sm text-red-600">{{ authStore.error }}</p>
            <Button type="submit" :loading="authStore.loading" class="w-full">Entrar</Button>
        </form>
        <template #footer>
            <p class="text-sm text-center text-gray-500 w-full">
                Nao tem conta?
                <Link href="/register" class="text-indigo-600 hover:text-indigo-800 font-medium">Criar conta</Link>
            </p>
        </template>
    </Card>
</template>
