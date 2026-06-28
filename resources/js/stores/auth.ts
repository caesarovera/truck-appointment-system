import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api, getAuthToken, setAuthToken } from '@/api/client';
import type { AuthUser, LoginResponse, MeResponse } from '@/types/api';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<AuthUser | null>(null);
    const token = ref<string | null>(getAuthToken());
    const initializing = ref(false);

    const isAuthenticated = computed(() => token.value !== null);

    function setToken(value: string | null): void {
        token.value = value;
        setAuthToken(value);
    }

    function clearSession(): void {
        setToken(null);
        user.value = null;
    }

    async function login(email: string, password: string): Promise<void> {
        const { data } = await api.post<LoginResponse>('/login', { email, password });
        setToken(data.token);
        user.value = data.user;
    }

    async function fetchMe(): Promise<void> {
        const { data } = await api.get<MeResponse>('/me');
        user.value = data.data;
    }

    async function logout(): Promise<void> {
        try {
            await api.post('/logout');
        } finally {
            clearSession();
        }
    }

    /** Pulihkan sesi saat reload: ada token tapi user belum dimuat → ambil /me. */
    async function restore(): Promise<void> {
        if (token.value === null || user.value !== null) {
            return;
        }
        initializing.value = true;
        try {
            await fetchMe();
        } catch {
            clearSession();
        } finally {
            initializing.value = false;
        }
    }

    function can(permission: string): boolean {
        return user.value?.permissions.includes(permission) ?? false;
    }

    function hasRole(role: string): boolean {
        return user.value?.roles.includes(role) ?? false;
    }

    return {
        user,
        token,
        initializing,
        isAuthenticated,
        login,
        fetchMe,
        logout,
        restore,
        clearSession,
        can,
        hasRole,
    };
});
