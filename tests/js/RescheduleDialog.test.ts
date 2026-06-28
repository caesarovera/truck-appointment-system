import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { Appointment, Gate, SlotWindow } from '@/types/api';

const gatesState = { gates: ref<Gate[]>([]), isLoading: ref(false), isError: ref(false) };
const availState = {
    windows: ref<SlotWindow[]>([]),
    isLoading: ref(false),
    isFetching: ref(false),
    isError: ref(false),
    enabled: ref(true),
};
const reschedule = { mutateAsync: vi.fn(), isPending: ref(false) };

vi.mock('@/composables/useGates', () => ({ useGates: () => gatesState }));
vi.mock('@/composables/useSlotAvailability', () => ({ useSlotAvailability: () => availState }));
vi.mock('@/composables/useRescheduleAppointment', () => ({ useRescheduleAppointment: () => reschedule }));

import RescheduleDialog from '@/components/RescheduleDialog.vue';

function slotWindow(overrides: Partial<SlotWindow>): SlotWindow {
    return {
        id: 10,
        gate_id: 1,
        date: '2026-06-28',
        start_time: '08:00:00',
        end_time: '09:00:00',
        capacity: 10,
        booked_count: 3,
        remaining: 7,
        status: 'OPEN',
        ...overrides,
    };
}

const appointment: Appointment = {
    id: 7,
    booking_code: 'TAS-AAA',
    status: 'CONFIRMED',
    move_type: 'DELIVERY',
    version: 3,
    company_id: 1,
    slot_window: slotWindow({ id: 5, gate_id: 1 }),
    truck: null,
    driver: null,
    containers: [],
    created_at: null,
};

const mountDialog = () => mount(RescheduleDialog, { props: { appointment } });

beforeEach(() => {
    gatesState.gates.value = [{ id: 1, terminal_id: 1, code: 'GATE-A', name: 'Gate A' }];
    availState.windows.value = [];
    availState.enabled.value = true;
    availState.isLoading.value = false;
    availState.isError.value = false;
    reschedule.isPending.value = false;
    vi.clearAllMocks();
});

describe('RescheduleDialog', () => {
    it('lists the available target windows', () => {
        availState.windows.value = [slotWindow({ id: 11 }), slotWindow({ id: 12, start_time: '09:00:00', end_time: '10:00:00' })];

        expect(mountDialog().findAll('[data-testid="window-option"]')).toHaveLength(2);
    });

    it('blocks submit until a target window is selected', async () => {
        availState.windows.value = [slotWindow({ id: 11 })];

        const wrapper = mountDialog();
        await wrapper.find('[data-testid="reschedule-submit"]').trigger('click');
        await flushPromises();

        expect(reschedule.mutateAsync).not.toHaveBeenCalled();
        expect(wrapper.find('[role="alert"]').text()).toContain('Pilih window tujuan');
    });

    it('reschedules to the selected window with the appointment version', async () => {
        reschedule.mutateAsync.mockResolvedValue({ ...appointment, slot_window: slotWindow({ id: 11 }) });
        availState.windows.value = [slotWindow({ id: 11 })];

        const wrapper = mountDialog();
        await wrapper.find('[data-testid="window-option"]').trigger('click');
        await wrapper.find('[data-testid="reschedule-submit"]').trigger('click');
        await flushPromises();

        expect(reschedule.mutateAsync).toHaveBeenCalledWith({ id: 7, slotWindowId: 11, version: 3 });
        expect(wrapper.emitted('rescheduled')).toBeTruthy();
    });

    it('maps a version conflict (409) to a friendly message', async () => {
        reschedule.mutateAsync.mockRejectedValue({
            isAxiosError: true,
            response: { data: { error: 'version_conflict' } },
        });
        availState.windows.value = [slotWindow({ id: 11 })];

        const wrapper = mountDialog();
        await wrapper.find('[data-testid="window-option"]').trigger('click');
        await wrapper.find('[data-testid="reschedule-submit"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('Booking sudah berubah');
        expect(wrapper.emitted('rescheduled')).toBeUndefined();
    });
});
