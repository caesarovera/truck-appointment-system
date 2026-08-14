import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { RoleWithPermissions } from '@/types/api';

// AdminPage panggil semua composable admin sekaligus di setup (bukan lazy per
// tab) — jadi semuanya harus di-mock walau test ini cuma fokus ke tab Role &
// Izin. Bentuk tiap mock persis apa yang di-destructure di skrip halaman.
const emptyList = () => ({ data: ref([]), isLoading: ref(false) });
const emptyCrud = () => ({
    ...emptyList(),
    create: { mutateAsync: vi.fn(), isPending: ref(false) },
    update: { mutateAsync: vi.fn(), isPending: ref(false) },
    remove: { mutateAsync: vi.fn(), isPending: ref(false) },
});

const rolesState = {
    data: ref<{ roles: RoleWithPermissions[]; allPermissions: string[] } | undefined>(undefined),
    isLoading: ref(false),
};
const updatePermissions = { mutateAsync: vi.fn(), isPending: ref(false) };

vi.mock('@/composables/useAdmin', () => ({
    useTerminals: () => emptyCrud(),
    useAdminGates: () => emptyCrud(),
    useCompanies: () => emptyCrud(),
    useUsers: () => emptyCrud(),
    useAdminRefs: () => ({ terminals: emptyList(), companies: emptyList() }),
    useRoles: () => ({ ...rolesState, updatePermissions }),
}));

import AdminPage from '@/pages/AdminPage.vue';

function roles(): RoleWithPermissions[] {
    return [
        { name: 'admin', immutable: true, permissions: ['user.manage', 'role.manage', 'slot.manage'] },
        { name: 'planner', immutable: false, permissions: ['slot.manage', 'report.read'] },
    ];
}

const mountPage = () => mount(AdminPage);

async function openRolesTab(wrapper: ReturnType<typeof mountPage>) {
    const tabs = wrapper.findAll('button').filter((b) => b.text() === 'roles');
    await tabs[0]?.trigger('click');
}

beforeEach(() => {
    rolesState.data.value = { roles: roles(), allPermissions: ['user.manage', 'role.manage', 'slot.manage', 'report.read'] };
    rolesState.isLoading.value = false;
    updatePermissions.isPending.value = false;
    vi.clearAllMocks();
});

describe('AdminPage — Role & Izin', () => {
    it('renders one row per role with a checkbox per permission', async () => {
        const wrapper = mountPage();
        await openRolesTab(wrapper);

        const rows = wrapper.findAll('[data-testid="role-row"]');
        expect(rows).toHaveLength(2);
        expect(wrapper.text()).toContain('admin');
        expect(wrapper.text()).toContain('planner');
        // 2 role x 4 permission = 8 checkbox.
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(8);
    });

    it('marks the admin row immutable: no Simpan button, checkboxes disabled', async () => {
        const wrapper = mountPage();
        await openRolesTab(wrapper);

        const adminRow = wrapper.findAll('[data-testid="role-row"]')[0];
        expect(adminRow?.text()).toContain('tak bisa diubah');
        expect(adminRow?.find('[data-testid="save-role"]').exists()).toBe(false);
        for (const checkbox of adminRow?.findAll('input[type="checkbox"]') ?? []) {
            expect((checkbox.element as HTMLInputElement).disabled).toBe(true);
        }
    });

    it('toggles a permission then saves the full updated list for a non-admin role', async () => {
        updatePermissions.mutateAsync.mockResolvedValue({});
        const wrapper = mountPage();
        await openRolesTab(wrapper);

        const plannerRow = wrapper.findAll('[data-testid="role-row"]')[1];
        // Centang 1 permission baru (appointment.override tak ada di list contoh
        // ini, jadi pakai yang memang ada: 'user.manage' belum dipegang planner).
        const checkboxes = plannerRow?.findAll('input[type="checkbox"]') ?? [];
        const labels = plannerRow?.findAll('label') ?? [];
        const targetIndex = labels.findIndex((l) => l.text().includes('user.manage'));
        await checkboxes[targetIndex]?.setValue(true);

        await plannerRow?.find('[data-testid="save-role"]').trigger('click');
        await flushPromises();

        expect(updatePermissions.mutateAsync).toHaveBeenCalledWith({
            name: 'planner',
            permissions: expect.arrayContaining(['slot.manage', 'report.read', 'user.manage']),
        });
    });

    it('shows a loading skeleton while roles are being fetched', async () => {
        rolesState.isLoading.value = true;
        rolesState.data.value = undefined;

        const wrapper = mountPage();
        await openRolesTab(wrapper);

        expect(wrapper.find('[data-testid="role-list"]').exists()).toBe(false);
    });
});
