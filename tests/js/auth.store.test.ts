import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { api } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import type { AuthUser } from '@/types/api';

// Isolasi store dari jaringan & localStorage (client di-mock penuh).
vi.mock('@/api/client', () => ({
    api: { post: vi.fn(), get: vi.fn() },
    setAuthToken: vi.fn(),
    getAuthToken: vi.fn(() => null),
}));

const demoUser: AuthUser = {
    id: 1,
    name: 'Planner',
    email: 'planner@tas.test',
    company_id: null,
    terminal_id: null,
    roles: ['planner'],
    permissions: ['slot.manage', 'slot.read'],
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('auth store', () => {
    it('logs in: stores token and user, becomes authenticated', async () => {
        (api.post as Mock).mockResolvedValue({
            data: { token: 'tok-123', token_type: 'Bearer', user: demoUser },
        });

        const auth = useAuthStore();
        await auth.login('planner@tas.test', 'password');

        expect(api.post).toHaveBeenCalledWith('/login', {
            email: 'planner@tas.test',
            password: 'password',
        });
        expect(auth.token).toBe('tok-123');
        expect(auth.user?.email).toBe('planner@tas.test');
        expect(auth.isAuthenticated).toBe(true);
    });

    it('derives can() and hasRole() from the user permissions/roles', async () => {
        (api.post as Mock).mockResolvedValue({
            data: { token: 't', token_type: 'Bearer', user: demoUser },
        });

        const auth = useAuthStore();
        await auth.login('x', 'y');

        expect(auth.can('slot.manage')).toBe(true);
        expect(auth.can('gate.process')).toBe(false);
        expect(auth.hasRole('planner')).toBe(true);
        expect(auth.hasRole('admin')).toBe(false);
    });

    it('logout clears the session', async () => {
        (api.post as Mock).mockResolvedValue({ data: {} });

        const auth = useAuthStore();
        auth.token = 'tok';
        auth.user = demoUser;

        await auth.logout();

        expect(api.post).toHaveBeenCalledWith('/logout');
        expect(auth.token).toBeNull();
        expect(auth.user).toBeNull();
        expect(auth.isAuthenticated).toBe(false);
    });

    it('restore() fetches /me when a token exists but user is missing', async () => {
        (api.get as Mock).mockResolvedValue({ data: { data: demoUser } });

        const auth = useAuthStore();
        auth.token = 'tok'; // simulasi token tersimpan dari sesi sebelumnya

        await auth.restore();

        expect(api.get).toHaveBeenCalledWith('/me');
        expect(auth.user?.id).toBe(1);
    });

    it('restore() clears the session when /me fails (token invalid)', async () => {
        (api.get as Mock).mockRejectedValue(new Error('unauthorized'));

        const auth = useAuthStore();
        auth.token = 'bad-token';

        await auth.restore();

        expect(auth.user).toBeNull();
        expect(auth.token).toBeNull();
    });
});
