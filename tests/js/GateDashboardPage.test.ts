import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { Appointment, SlotWindow } from '@/types/api';

const queue = {
    appointments: ref<Appointment[]>([]),
    isLoading: ref(false),
    isError: ref(false),
};
const gateInMutation = { mutateAsync: vi.fn(), isPending: ref(false) };
const gateOutMutation = { mutateAsync: vi.fn(), isPending: ref(false) };

vi.mock('@/composables/useGateQueue', () => ({
    useGateQueue: () => queue,
    useGateIn: () => gateInMutation,
    useGateOut: () => gateOutMutation,
}));

import GateDashboardPage from '@/pages/GateDashboardPage.vue';

function appointment(id: number, status: string, startTime: string): Appointment {
    const slotWindow: SlotWindow = {
        id,
        gate_id: 1,
        date: '2026-06-28',
        start_time: startTime,
        end_time: '12:00:00',
        capacity: 10,
        booked_count: 1,
        remaining: 9,
        status: 'OPEN',
        gate: { id: 1, terminal_id: 1, code: 'GATE-A', name: 'Gate A' },
    };

    return {
        id,
        booking_code: `TAS-${id}`,
        status,
        move_type: 'DELIVERY',
        version: 1,
        company_id: 1,
        slot_window: slotWindow,
        truck: { id: 1, plate_no: 'B 9011 XX', status: 'ACTIVE' },
        driver: null,
        containers: [],
        created_at: null,
    };
}

const mountPage = () => mount(GateDashboardPage, { global: { stubs: { RouterLink: true } } });

beforeEach(() => {
    queue.appointments.value = [];
    queue.isLoading.value = false;
    queue.isError.value = false;
    gateInMutation.isPending.value = false;
    gateOutMutation.isPending.value = false;
    vi.clearAllMocks();
});

describe('GateDashboardPage', () => {
    it('shows an empty state when the queue is empty', () => {
        expect(mountPage().text()).toContain('Tidak ada truk di antrian');
    });

    it('offers Gate In for CONFIRMED and Gate Out for IN_PROGRESS', () => {
        queue.appointments.value = [appointment(1, 'CONFIRMED', '08:00:00'), appointment(2, 'IN_PROGRESS', '09:00:00')];

        const wrapper = mountPage();

        expect(wrapper.findAll('[data-testid="gate-in"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="gate-out"]')).toHaveLength(1);
    });

    it('gates a truck in when the button is clicked', async () => {
        gateInMutation.mutateAsync.mockResolvedValue({});
        queue.appointments.value = [appointment(7, 'CONFIRMED', '08:00:00')];

        const wrapper = mountPage();
        await wrapper.find('[data-testid="gate-in"]').trigger('click');
        await flushPromises();

        expect(gateInMutation.mutateAsync).toHaveBeenCalledWith(7);
    });

    it('maps an invalid-state error (409) to a friendly message', async () => {
        gateOutMutation.mutateAsync.mockRejectedValue({
            isAxiosError: true,
            response: { data: { error: 'invalid_state' } },
        });
        queue.appointments.value = [appointment(7, 'IN_PROGRESS', '08:00:00')];

        const wrapper = mountPage();
        await wrapper.find('[data-testid="gate-out"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('Status tidak sesuai');
    });
});
