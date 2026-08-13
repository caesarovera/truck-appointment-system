import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, RouterLinkStub } from '@vue/test-utils';
import { ref, type Ref } from 'vue';
import type { AuthUser } from '@/types/api';

const user: Ref<Partial<AuthUser> | null> = ref(null);
let allowed = new Set<string>();
let roles = new Set<string>();

// Sama pola dengan AppNav.test.ts: gating link = can(perm) + (utk /today)
// hasRole('driver'). DashboardPage jadi tempat KEDUA yang gate identik dengan
// AppNav — dua tempat itu kartu/link yang sama harus konsisten gating-nya.
vi.mock('@/stores/auth', () => ({
    useAuthStore: () => ({
        can: (perm: string) => allowed.has(perm),
        hasRole: (role: string) => roles.has(role),
        get user() {
            return user.value;
        },
    }),
}));

import DashboardPage from '@/pages/DashboardPage.vue';

const mountPage = () => mount(DashboardPage, { global: { stubs: { RouterLink: RouterLinkStub } } });

beforeEach(() => {
    allowed = new Set();
    roles = new Set();
    user.value = { name: 'Uji', permissions: [], roles: [], company_id: null, terminal_id: null };
});

describe('DashboardPage', () => {
    it('hides company/terminal/driver-scoped cards for admin even though admin has every permission', () => {
        // Sama skenario dgn AppNav: admin (RolePermissionSeeder) punya SEMUA
        // permission tapi company_id/terminal_id null & bukan role driver.
        allowed = new Set(['appointment.write', 'fleet.manage', 'gate.process', 'appointment.read.self', 'terminal.manage']);
        user.value = { name: 'Admin', permissions: [], roles: ['admin'], company_id: null, terminal_id: null };

        const text = mountPage().text();

        expect(text).not.toContain('Booking Saya');
        expect(text).not.toContain('Armada Truk');
        expect(text).not.toContain('Dashboard Gate');
        expect(text).not.toContain('Jadwal Hari Ini');
        expect(text).toContain('Master Data'); // terminal.manage tak butuh identitas apa pun
    });

    it('shows the cards for the actual persona each is meant for', () => {
        allowed = new Set(['appointment.write', 'fleet.manage']);
        user.value = { name: 'Dispatcher', permissions: [], roles: ['transporter'], company_id: 7, terminal_id: null };

        const text = mountPage().text();

        expect(text).toContain('Booking Saya');
        expect(text).toContain('Armada Truk');
    });

    it('shows Jadwal Hari Ini only for an actual driver', () => {
        allowed = new Set(['appointment.read.self']);
        roles = new Set(['driver']);
        user.value = { name: 'Budi', permissions: [], roles: ['driver'], company_id: null, terminal_id: null };

        expect(mountPage().text()).toContain('Jadwal Hari Ini');
    });

    it('shows Dashboard Gate only for a gate-officer with a terminal', () => {
        allowed = new Set(['gate.process']);
        user.value = { name: 'Petugas', permissions: [], roles: ['gate-officer'], company_id: null, terminal_id: 3 };

        expect(mountPage().text()).toContain('Dashboard Gate');
    });
});
