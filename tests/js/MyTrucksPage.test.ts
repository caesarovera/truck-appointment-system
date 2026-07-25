import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { Truck } from '@/types/api';

// Composable di-mock jadi state terkontrol (pola GateDashboardPage).
const state = {
    trucks: ref<Truck[]>([]),
    isLoading: ref(false),
    isError: ref(false),
    create: { mutateAsync: vi.fn(), isPending: ref(false) },
    update: { mutateAsync: vi.fn(), isPending: ref(false) },
    remove: { mutateAsync: vi.fn(), isPending: ref(false) },
};

vi.mock('@/composables/useTrucks', () => ({ useMyTrucks: () => state }));

import MyTrucksPage from '@/pages/MyTrucksPage.vue';

function truck(id: number, plate: string, status = 'ACTIVE'): Truck {
    return { id, plate_no: plate, status };
}

const mountPage = () => mount(MyTrucksPage);

beforeEach(() => {
    state.trucks.value = [];
    state.isLoading.value = false;
    state.isError.value = false;
    state.create.isPending.value = false;
    state.update.isPending.value = false;
    state.remove.isPending.value = false;
    vi.clearAllMocks();
});

describe('MyTrucksPage', () => {
    it('shows an empty state when there are no trucks', () => {
        expect(mountPage().text()).toContain('Belum ada truk');
    });

    it('lists trucks with their status', () => {
        state.trucks.value = [truck(1, 'B 1 X'), truck(2, 'B 2 Y', 'INACTIVE')];

        const wrapper = mountPage();

        expect(wrapper.findAll('[data-testid="truck-row"]')).toHaveLength(2);
        expect(wrapper.text()).toContain('B 1 X');
        expect(wrapper.text()).toContain('INACTIVE');
    });

    it('creates a truck on form submit', async () => {
        state.create.mutateAsync.mockResolvedValue({});

        const wrapper = mountPage();
        await wrapper.find('[data-testid="plate-input"]').setValue('B 9 ZZ');
        await wrapper.find('[data-testid="status-select"]').setValue('INACTIVE');
        await wrapper.find('[data-testid="truck-form"]').trigger('submit');
        await flushPromises();

        expect(state.create.mutateAsync).toHaveBeenCalledWith({ plate_no: 'B 9 ZZ', status: 'INACTIVE' });
    });

    it('populates the form for editing and calls update', async () => {
        state.trucks.value = [truck(3, 'B 3 GH')];
        state.update.mutateAsync.mockResolvedValue({});

        const wrapper = mountPage();
        await wrapper.find('[data-testid="edit-truck"]').trigger('click');

        expect((wrapper.find('[data-testid="plate-input"]').element as HTMLInputElement).value).toBe('B 3 GH');

        await wrapper.find('[data-testid="status-select"]').setValue('INACTIVE');
        await wrapper.find('[data-testid="truck-form"]').trigger('submit');
        await flushPromises();

        expect(state.update.mutateAsync).toHaveBeenCalledWith({ id: 3, plate_no: 'B 3 GH', status: 'INACTIVE' });
    });

    it('confirms before deleting, then calls remove', async () => {
        state.trucks.value = [truck(4, 'B 4 KK')];
        state.remove.mutateAsync.mockResolvedValue(undefined);

        const wrapper = mountPage();
        await wrapper.find('[data-testid="delete-truck"]').trigger('click');
        await wrapper.find('[data-testid="confirm-delete"]').trigger('click');
        await flushPromises();

        expect(state.remove.mutateAsync).toHaveBeenCalledWith(4);
    });

    it('maps a 409 entity_in_use delete error to a friendly message', async () => {
        state.trucks.value = [truck(5, 'B 5 LL')];
        state.remove.mutateAsync.mockRejectedValue({
            isAxiosError: true,
            response: { status: 409, data: { error: 'entity_in_use' } },
        });

        const wrapper = mountPage();
        await wrapper.find('[data-testid="delete-truck"]').trigger('click');
        await wrapper.find('[data-testid="confirm-delete"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('masih dipakai');
    });
});
