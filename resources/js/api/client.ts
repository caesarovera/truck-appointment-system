import axios, { type AxiosError } from 'axios';
import type { VueQueryPluginOptions } from '@tanstack/vue-query';

const TOKEN_KEY = 'tas_token';

// Sumber kebenaran token di level modul supaya interceptor tak perlu impor store
// (cegah circular import). Store tetap menyetelnya lewat setAuthToken().
let authToken: string | null = localStorage.getItem(TOKEN_KEY);

export function setAuthToken(token: string | null): void {
    authToken = token;
    if (token !== null) {
        localStorage.setItem(TOKEN_KEY, token);
    } else {
        localStorage.removeItem(TOKEN_KEY);
    }
}

export function getAuthToken(): string | null {
    return authToken;
}

export const api = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
    if (authToken !== null) {
        config.headers.Authorization = `Bearer ${authToken}`;
    }
    return config;
});

/**
 * Kebijakan retry query TanStack. 4xx = jawaban server yang tak akan berubah bila
 * diulang (403 role, 404, 422, 429) → langsung tampil, tak dicoba ulang 3× dengan
 * backoff (dulu ±7 dtk skeleton & 20 request 403 per halaman). Kecuali 408
 * (timeout = gangguan sesaat). Jaringan putus / 5xx tetap diulang seperti default (3×).
 */
export function shouldRetryQuery(failureCount: number, error: unknown): boolean {
    const status = axios.isAxiosError(error) ? error.response?.status : undefined;
    if (status !== undefined && status >= 400 && status < 500 && status !== 408) {
        return false;
    }
    return failureCount < 3;
}

/** Opsi VueQueryPlugin di app.ts — di sini supaya bisa diuji (app.ts = entry ber-efek samping). */
export const vueQueryOptions = {
    queryClientConfig: { defaultOptions: { queries: { retry: shouldRetryQuery } } },
} satisfies VueQueryPluginOptions;

// Hook 401 di-set app.ts → bisa redirect ke /login tanpa circular import.
let onUnauthorized: (() => void) | null = null;

export function setUnauthorizedHandler(handler: () => void): void {
    onUnauthorized = handler;
}

api.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
        if (error.response?.status === 401) {
            setAuthToken(null);
            onUnauthorized?.();
        }
        return Promise.reject(error);
    },
);
