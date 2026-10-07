import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { AxiosError } from 'axios';
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

const terminalsCrud = emptyCrud();
const usersCrud = emptyCrud();

const rolesState = {
    data: ref<{ roles: RoleWithPermissions[]; allPermissions: string[] } | undefined>(undefined),
    isLoading: ref(false),
};
const updatePermissions = { mutateAsync: vi.fn(), isPending: ref(false) };

vi.mock('@/composables/useAdmin', () => ({
    useTerminals: () => terminalsCrud,
    useAdminGates: () => emptyCrud(),
    useCompanies: () => emptyCrud(),
    useUsers: () => usersCrud,
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
    terminalsCrud.create.mutateAsync.mockReset();
    usersCrud.create.mutateAsync.mockReset();
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

// Error simpan (422 dll.) sebelumnya TIDAK tampil: handler submit tak punya catch → rejection tak
// tertangani, admin melihat form diam saja. Terbukti makin terasa setelah kebijakan password baru.
function axiosError(status: number, data: unknown): AxiosError {
    return new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, {
        status,
        data,
        statusText: '',
        headers: {},
        config: {} as never,
    });
}

async function openTab(wrapper: ReturnType<typeof mountPage>, name: string) {
    await wrapper.findAll('button').filter((b) => b.text() === name)[0]?.trigger('click');
}

describe('AdminPage — error simpan', () => {
    it('shows the first validation message on 422 and keeps what the admin typed', async () => {
        usersCrud.create.mutateAsync.mockRejectedValue(
            axiosError(422, { message: 'x', errors: { password: ['The password field must contain at least one uppercase and one lowercase letter.'] } }),
        );
        const wrapper = mountPage();
        await openTab(wrapper, 'users');

        await wrapper.find('input[placeholder="Nama"]').setValue('Budi');
        await wrapper.find('input[placeholder="Email"]').setValue('budi@tas.test');
        await wrapper.find('input[placeholder="Password"]').setValue('lemah');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('uppercase and one lowercase');
        expect((wrapper.find('input[placeholder="Nama"]').element as HTMLInputElement).value).toBe('Budi');
    });

    it('clears the form and shows no alert after a successful save', async () => {
        usersCrud.create.mutateAsync.mockResolvedValue({});
        const wrapper = mountPage();
        await openTab(wrapper, 'users');

        await wrapper.find('input[placeholder="Nama"]').setValue('Budi');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect((wrapper.find('input[placeholder="Nama"]').element as HTMLInputElement).value).toBe('');
    });

    it('shows the API message for non-422 errors and a generic one for non-HTTP errors (other tabs too)', async () => {
        terminalsCrud.create.mutateAsync.mockRejectedValueOnce(axiosError(409, { message: 'Kode terminal sudah dipakai.' }));
        const wrapper = mountPage();

        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').text()).toContain('Kode terminal sudah dipakai.');

        terminalsCrud.create.mutateAsync.mockRejectedValueOnce(new Error('network down'));
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').text()).toContain('Terjadi kesalahan');
    });

    it('drops a stale error when switching tab', async () => {
        terminalsCrud.create.mutateAsync.mockRejectedValue(axiosError(409, { message: 'Kode terminal sudah dipakai.' }));
        const wrapper = mountPage();
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);

        await openTab(wrapper, 'users');
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('tells the admin the password policy on the create-user form', async () => {
        const wrapper = mountPage();
        await openTab(wrapper, 'users');

        expect(wrapper.text()).toContain('minimal 10 karakter');
    });
});
