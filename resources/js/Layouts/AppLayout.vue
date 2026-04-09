<script setup>
import { ref, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useAuthStore } from '../Stores/auth.js';
import ToastContainer from '../Components/ToastContainer.vue';

const authStore = useAuthStore();
const mobileMenuOpen = ref(false);
const page = usePage();

onMounted(async () => {
    if (!authStore.isAuthenticated) {
        router.visit('/login');
        return;
    }
    if (!authStore.user) {
        await authStore.fetchUser();
    }
});

async function handleLogout() {
    await authStore.logout();
    router.visit('/login');
}

function isActive(path) {
    return page.url.startsWith(path);
}

const navLinks = [
    { href: '/dashboard', label: 'Dashboard' },
    { href: '/teams', label: 'Equipes' },
];
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <Link href="/dashboard" class="text-xl font-bold text-indigo-600">
                            Caixinha
                        </Link>
                        <div class="hidden sm:flex sm:ml-8 sm:gap-1">
                            <Link
                                v-for="link in navLinks"
                                :key="link.href"
                                :href="link.href"
                                :class="[
                                    'inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                                    isActive(link.href)
                                        ? 'text-indigo-600 bg-indigo-50'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50',
                                ]"
                            >
                                {{ link.label }}
                            </Link>
                        </div>
                    </div>
                    <div class="hidden sm:flex sm:items-center sm:gap-4">
                        <span class="text-sm text-gray-700">{{ authStore.userName }}</span>
                        <button
                            @click="handleLogout"
                            class="text-sm text-gray-500 hover:text-gray-700 transition-colors"
                        >
                            Sair
                        </button>
                    </div>
                    <div class="flex items-center sm:hidden">
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            class="p-2 rounded-lg text-gray-500 hover:bg-gray-100"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="mobileMenuOpen" class="sm:hidden border-t border-gray-200">
                <div class="px-4 py-3 space-y-1">
                    <Link
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        :class="[
                            'block px-3 py-2 text-base font-medium rounded-lg',
                            isActive(link.href)
                                ? 'text-indigo-600 bg-indigo-50'
                                : 'text-gray-600 hover:bg-gray-50',
                        ]"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.label }}
                    </Link>
                </div>
                <div class="px-4 py-3 border-t border-gray-200">
                    <p class="text-sm text-gray-700 mb-2">{{ authStore.userName }}</p>
                    <button @click="handleLogout" class="text-sm text-red-600 hover:text-red-800">Sair</button>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <slot />
        </main>

        <ToastContainer />
    </div>
</template>
