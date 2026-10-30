import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import type { AuthUser } from '@/types/api';
import { router } from '@/router';
import { api } from '@/api/client';
import { canVisit, GUARDED_ROUTES } from '@/router/access';
import { useAuthStore } from '@/stores/auth';

// Router & store SUNGGUHAN (guard beforeEach yang diuji, bukan mock-nya).
// user diisi langsung → restore() (/me) tak pernah dipanggil, tanpa jaringan.
function signIn(user: Partial<AuthUser> | null): void {
    const auth = useAuthStore();
    auth.token = user === null ? null : 'test-token';
    auth.user = user === null ? null : ({ id: 1, name: 'Uji', email: 'uji@test', company_id: null, terminal_id: null, roles: [], permissions: [], ...user } as AuthUser);
}

const driver = { roles: ['driver'], permissions: ['appointment.read.self'], company_id: 1 };
const admin = { roles: ['admin'], permissions: ['terminal.manage', 'slot.manage', 'slot.read', 'appointment.write', 'fleet.manage', 'gate.process', 'report.read', 'appointment.read.self'] };

beforeEach(async () => {
    setActivePinia(createPinia());
    signIn(null);
    await router.push('/login');
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('router guard', () => {
    it('sends a driver opening /admin to the forbidden page, remembering where they came from', async () => {
        signIn(driver);

        await router.push('/admin');

        expect(router.currentRoute.value.name).toBe('forbidden');
        expect(router.currentRoute.value.query.from).toBe('/admin');
    });

    it('lets an admin open /admin', async () => {
        signIn(admin);

        await router.push('/admin');

        expect(router.currentRoute.value.name).toBe('admin');
    });

    it('forbids /bookings for an admin who has the permission but no company (same rule as the navbar)', async () => {
        signIn(admin);

        await router.push('/bookings');

        expect(router.currentRoute.value.name).toBe('forbidden');
    });

    it('lets a driver open /today', async () => {
        signIn(driver);

        await router.push('/today');

        expect(router.currentRoute.value.name).toBe('today');
    });

    it('shows not-found for an unknown path when signed in, keeping the URL', async () => {
        signIn(driver);

        await router.push('/tidak-ada/sama-sekali');

        expect(router.currentRoute.value.name).toBe('not-found');
        expect(router.currentRoute.value.fullPath).toBe('/tidak-ada/sama-sekali');
    });

    it('sends a guest on an unknown path to login first', async () => {
        await router.push('/tidak-ada');

        expect(router.currentRoute.value.name).toBe('login');
        expect(router.currentRoute.value.query.redirect).toBe('/tidak-ada');
    });

    it('opens the forbidden page itself without redirecting again', async () => {
        signIn(driver);

        await router.push({ name: 'forbidden', query: { from: '/admin' } });

        expect(router.currentRoute.value.name).toBe('forbidden');
    });
});

// Reload halaman: token ada di localStorage, user belum dimuat → guard WAJIB
// restore() (/me) dulu sebelum menilai akses.
describe('router guard after a page reload', () => {
    function reloadWithToken(): void {
        const auth = useAuthStore();
        auth.token = 'stored-token';
        auth.user = null;
    }

    it('restores the user first, then judges access with the restored roles', async () => {
        reloadWithToken();
        const me = vi.spyOn(api, 'get').mockResolvedValue({
            data: { data: { id: 9, name: 'Budi', email: 'b@test', terminal_id: null, ...driver } },
        });

        await router.push('/admin');

        expect(me).toHaveBeenCalledWith('/me');
        expect(router.currentRoute.value.name).toBe('forbidden');

        await router.push('/today');
        expect(router.currentRoute.value.name).toBe('today');
    });

    it('sends the user to login (keeping the target) when the stored token is no longer valid', async () => {
        reloadWithToken();
        vi.spyOn(api, 'get').mockRejectedValue(new Error('401'));

        await router.push('/admin');

        expect(router.currentRoute.value.name).toBe('login');
        expect(router.currentRoute.value.query.redirect).toBe('/admin');
        expect(useAuthStore().token).toBeNull();
    });
});

// access.test.ts menguji tabel aturan; ini memastikan tabel itu benar-benar
// TERPASANG di router — nama rute salah ketik = guard mati diam-diam.
describe('every guarded route is wired into the router', () => {
    it.each(GUARDED_ROUTES)('route "%s" exists in the router', (name) => {
        expect(router.hasRoute(name)).toBe(true);
    });

    for (const [persona, user] of Object.entries({ driver, admin })) {
        it.each(GUARDED_ROUTES)(`${persona} on "%s" lands exactly where canVisit says`, async (name) => {
            signIn(user);
            const auth = useAuthStore();

            await router.push(router.resolve({ name }).fullPath);

            expect(router.currentRoute.value.name).toBe(canVisit(name, auth) ? name : 'forbidden');
        });
    }
});
