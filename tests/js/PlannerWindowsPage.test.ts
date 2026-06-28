import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { Gate, SlotUtilization, UtilizationSummary } from '@/types/api';

const gatesState = { gates: ref<Gate[]>([]), isLoading: ref(false), isError: ref(false) };
const util = {
    windows: ref<SlotUtilization[]>([]),
    summary: ref<UtilizationSummary | null>(null),
    isLoading: ref(false),
    isError: ref(false),
    enabled: ref(true),
};
const openMutation = { mutateAsync: vi.fn(), isPending: ref(false) };
const closeMutation = { mutateAsync: vi.fn(), isPending: ref(false) };

vi.mock('@/composables/useGates', () => ({ useGates: () => gatesState }));
vi.mock('@/composables/usePlannerWindows', () => ({
    useUtilization: () => util,
    useOpenSlotWindow: () => openMutation,
    useCloseSlotWindow: () => closeMutation,
}));

import PlannerWindowsPage from '@/pages/PlannerWindowsPage.vue';

function window(overrides: Partial<SlotUtilization>): SlotUtilization {
    return {
        id: 1,
        start_time: '08:00:00',
        end_time: '09:00:00',
        status: 'OPEN',
        capacity: 10,
        booked_count: 3,
        remaining: 7,
        no_show: 0,
        ...overrides,
    };
}

const mountPage = () => mount(PlannerWindowsPage, { global: { stubs: { RouterLink: true } } });

beforeEach(() => {
    gatesState.gates.value = [{ id: 1, terminal_id: 1, code: 'GATE-A', name: 'Gate A' }];
    util.windows.value = [];
    util.summary.value = null;
    util.enabled.value = true;
    util.isLoading.value = false;
    util.isError.value = false;
    openMutation.isPending.value = false;
    closeMutation.isPending.value = false;
    vi.clearAllMocks();
});

describe('PlannerWindowsPage', () => {
    it('lists windows and offers Close only for OPEN ones', () => {
        util.windows.value = [window({ id: 1, status: 'OPEN' }), window({ id: 2, status: 'CLOSED' })];

        const wrapper = mountPage();

        expect(wrapper.findAll('[data-testid="window-row"]')).toHaveLength(2);
        expect(wrapper.findAll('[data-testid="close-window"]')).toHaveLength(1);
    });

    it('validates the open form before calling the API', async () => {
        const wrapper = mountPage();

        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(openMutation.mutateAsync).not.toHaveBeenCalled();
        expect(wrapper.find('[role="alert"]').text()).toContain('Lengkapi gate');
    });

    it('opens a window with H:i:s times built from the form', async () => {
        openMutation.mutateAsync.mockResolvedValue({});
        const wrapper = mountPage();

        // gate dropdown (index 0), date (index 0), then time/capacity inputs.
        await wrapper.findAll('select')[0].setValue(1);
        const times = wrapper.findAll('input[type="time"]');
        await times[0].setValue('08:00');
        await times[1].setValue('09:00');
        await wrapper.find('input[type="number"]').setValue(10);

        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(openMutation.mutateAsync).toHaveBeenCalledTimes(1);
        const payload = openMutation.mutateAsync.mock.calls[0][0];
        expect(payload).toMatchObject({
            gate: 1,
            start_time: '08:00:00',
            end_time: '09:00:00',
            capacity: 10,
        });
    });

    it('closes a window when the button is clicked', async () => {
        closeMutation.mutateAsync.mockResolvedValue({});
        util.windows.value = [window({ id: 7, status: 'OPEN' })];

        const wrapper = mountPage();
        await wrapper.find('[data-testid="close-window"]').trigger('click');
        await flushPromises();

        expect(closeMutation.mutateAsync).toHaveBeenCalledWith(7);
    });

    it('maps a duplicate-window error (409) to a friendly message', async () => {
        openMutation.mutateAsync.mockRejectedValue({
            isAxiosError: true,
            response: { data: { error: 'duplicate_slot_window' } },
        });
        const wrapper = mountPage();

        await wrapper.findAll('select')[0].setValue(1);
        const times = wrapper.findAll('input[type="time"]');
        await times[0].setValue('08:00');
        await times[1].setValue('09:00');
        await wrapper.find('input[type="number"]').setValue(10);
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('sudah ada');
    });
});
