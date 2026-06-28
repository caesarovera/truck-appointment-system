<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

async function onLogout(): Promise<void> {
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
            <h1 class="font-semibold text-gray-900">TAS — Dashboard</h1>
            <button class="text-sm text-gray-600 hover:text-gray-900" @click="onLogout">Keluar</button>
        </header>

        <main class="p-6 space-y-2">
            <p class="text-gray-900">Halo, <strong>{{ auth.user?.name }}</strong></p>
            <p class="text-sm text-gray-600">Role: {{ auth.user?.roles.join(', ') || '—' }}</p>
            <p class="text-sm text-gray-600">{{ auth.user?.permissions.length ?? 0 }} izin aktif</p>
        </main>
    </div>
</template>
