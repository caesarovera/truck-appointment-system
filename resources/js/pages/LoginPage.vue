<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { isAxiosError } from 'axios';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const error = ref<string | null>(null);
const submitting = ref(false);

async function submit(): Promise<void> {
    error.value = null;
    submitting.value = true;
    try {
        await auth.login(email.value, password.value);
        const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/';
        await router.push(redirect);
    } catch (e) {
        error.value = extractError(e);
    } finally {
        submitting.value = false;
    }
}

function extractError(e: unknown): string {
    if (isAxiosError(e)) {
        const data = e.response?.data as { message?: string } | undefined;
        return data?.message ?? 'Login gagal. Periksa email & password.';
    }
    return 'Terjadi kesalahan. Coba lagi.';
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <form class="w-full max-w-sm bg-white rounded-xl shadow p-8 space-y-5" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-gray-900">Masuk — TAS</h1>

            <p v-if="error" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-2">
                {{ error }}
            </p>

            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700">Email</span>
                <input
                    v-model="email"
                    type="email"
                    required
                    autocomplete="username"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </label>

            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700">Password</span>
                <input
                    v-model="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </label>

            <button
                type="submit"
                :disabled="submitting"
                class="w-full rounded-md bg-indigo-600 text-white py-2 font-medium hover:bg-indigo-700 disabled:opacity-50"
            >
                {{ submitting ? 'Memproses…' : 'Masuk' }}
            </button>
        </form>
    </div>
</template>
