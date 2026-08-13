import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { ref, type Ref } from 'vue';
import type { AuthUser } from '@/types/api';

const logout = vi.fn();
const push = vi.fn();
const user: Ref<Partial<AuthUser> | null> = ref(null);
let allowed = new Set<string>();
let roles = new Set<string>();

// Terisolasi: store & router di-mock (pola LoginPage). Gating link = can(perm)
// (+ hasRole('driver') khusus link /today — lihat komentar di AppNav.vue).
vi.mock('@/stores/auth', () => ({
    useAuthStore: () => ({
        logout,
        can: (perm: string) => allowed.has(perm),
        hasRole: (role: string) => roles.has(role),
        get user() {
            return user.value;
        },
    }),
}));
vi.mock('vue-router', async (importOriginal) => ({
    ...(await importOriginal<typeof import('vue-router')>()),
    useRouter: () => ({ push }),
}));

import AppNav from '@/components/AppNav.vue';

// RouterLinkStub merender isi slot (stub `true` tidak) → teks link bisa di-assert.
const mountNav = () => mount(AppNav, { global: { stubs: { RouterLink: RouterLinkStub } } });

beforeEach(() => {
    allowed = new Set();
    roles = new Set();
    user.value = { name: 'Uji', company_id: null };
    vi.clearAllMocks();
});

describe('AppNav', () => {
    it('shows only the links the user has permissions for (transporter)', () => {
        allowed = new Set(['slot.read', 'appointment.write', 'report.read']);
        user.value = { name: 'Dispatcher', company_id: 7 };

        const text = mountNav().find('[data-testid="nav-links"]').text();

        expect(text).toContain('Slot');
        expect(text).toContain('Booking Saya');
        expect(text).toContain('Laporan');
        expect(text).not.toContain('Gate');
        expect(text).not.toContain('Kelola Slot');
        expect(text).not.toContain('Master Data');
    });

    it('hides the Laporan link when report.read exists but the user has no company (planner)', () => {
        allowed = new Set(['report.read', 'slot.manage']);
        user.value = { name: 'Planner', company_id: null };

        const text = mountNav().find('[data-testid="nav-links"]').text();

        expect(text).not.toContain('Laporan');
        expect(text).toContain('Kelola Slot');
        expect(text).toContain('Riwayat Gate'); // slot.manage = actor sama dgn backend hasAnyRole(admin,planner)
    });

    it('hides company/terminal-scoped links for admin even though admin has every permission', () => {
        // Admin (RolePermissionSeeder) punya SEMUA permission tapi company_id
        // & terminal_id keduanya null — endpoint di baliknya 403 (bukan role,
        // tapi identitas: MyFleetController/MyAppointmentsController/GateQueueController
        // semua abort_if(...id === null)). Nav tak boleh menampilkan link yang
        // pasti gagal saat diklik.
        allowed = new Set([
            'appointment.write',
            'fleet.manage',
            'gate.process',
            'report.read',
            'appointment.read.self',
            'terminal.manage',
        ]);
        user.value = { name: 'Admin', company_id: null, terminal_id: null };
        // roles tetap kosong: admin bukan 'driver' (hasRole), sama seperti company_id/
        // terminal_id-nya null — keduanya identitas, bukan permission.

        const text = mountNav().find('[data-testid="nav-links"]').text();

        expect(text).not.toContain('Booking Saya');
        expect(text).not.toContain('Armada');
        expect(text).not.toContain('Gate');
        expect(text).not.toContain('Laporan');
        expect(text).not.toContain('Jadwal Hari Ini'); // punya izinnya tapi bukan driver
        expect(text).toContain('Master Data'); // terminal.manage tak butuh identitas apa pun
    });

    it('shows Jadwal Hari Ini only for an actual driver', () => {
        allowed = new Set(['appointment.read.self']);
        roles = new Set(['driver']);
        user.value = { name: 'Budi', company_id: null };

        expect(mountNav().find('[data-testid="nav-links"]').text()).toContain('Jadwal Hari Ini');
    });

    it('logs out then redirects to the login page', async () => {
        logout.mockResolvedValue(undefined);

        const wrapper = mountNav();
        await wrapper.find('[data-testid="logout"]').trigger('click');
        await flushPromises();

        expect(logout).toHaveBeenCalledOnce();
        expect(push).toHaveBeenCalledWith({ name: 'login' });
    });

    it('shows the current user name', () => {
        user.value = { name: 'Budi Santoso', company_id: 1 };

        expect(mountNav().find('[data-testid="user-name"]').text()).toBe('Budi Santoso');
    });
});
