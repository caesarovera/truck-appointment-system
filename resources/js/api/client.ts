import axios, { type AxiosError } from 'axios';

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
