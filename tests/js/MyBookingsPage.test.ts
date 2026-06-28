import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { Appointment } from '@/types/api';

const listState = {
    appointments: ref<Appointment[]>([]),
    isLoading: ref(false),
    isError: ref(false),
};
const cancelMutation = { mutateAsync: vi.fn(), isPending: ref(false) };

vi.mock('@/composables/useMyAppointments', () => ({ useMyAppointments: () => listState }));
vi.mock('@/composables/useCancelAppointment', () => ({ useCancelAppointment: () => cancelMutation }));

import MyBookingsPage from '@/pages/MyBookingsPage.vue';

function appointment(overrides: Partial<Appointment>): Appointment {
    return {
        id: 1,
        booking_code: 'TAS-AAA',
        status: 'CONFIRMED',
        move_type: 'DELIVERY',
        version: 1,
        company_id: 1,
        slot_window: {
            id: 5,
            gate_id: 1,
            date: '2026-06-28',
            start_time: '08:00:00',
            end_time: '09:00:00',
            capacity: 10,
            booked_count: 3,
            remaining: 7,
            status: 'OPEN',
        },
        truck: { id: 1, plate_no: 'B 9011 XX', status: 'ACTIVE' },
        driver: { id: 2, name: 'Budi' },
        containers: [{ id: 1, container_no: 'MAUU1234567', iso_type: '22G1', size: 20 }],
        created_at: null,
        ...overrides,
    };
}

const mountPage = () => mount(MyBookingsPage, { global: { stubs: { RouterLink: true } } });

beforeEach(() => {
    listState.appointments.value = [];
    listState.isLoading.value = false;
    listState.isError.value = false;
    cancelMutation.isPending.value = false;
    vi.clearAllMocks();
});

describe('MyBookingsPage', () => {
    it('renders a row per booking with code, slot and fleet', () => {
        listState.appointments.value = [appointment({ id: 1, booking_code: 'TAS-AAA' })];

        const wrapper = mountPage();

        expect(wrapper.findAll('[data-testid="booking-row"]')).toHaveLength(1);
        expect(wrapper.text()).toContain('TAS-AAA');
        expect(wrapper.text()).toContain('B 9011 XX');
        expect(wrapper.text()).toContain('MAUU1234567');
    });

    it('shows an empty state when there are no bookings', () => {
        expect(mountPage().text()).toContain('Belum ada booking');
    });

    it('only offers cancel for cancellable statuses', () => {
        listState.appointments.value = [
            appointment({ id: 1, status: 'CONFIRMED' }),
            appointment({ id: 2, status: 'COMPLETED' }),
        ];

        expect(mountPage().findAll('[data-testid="cancel-button"]')).toHaveLength(1);
    });

    it('confirms then cancels with the appointment version', async () => {
        cancelMutation.mutateAsync.mockResolvedValue({});
        listState.appointments.value = [appointment({ id: 7, version: 3, status: 'CONFIRMED' })];

        const wrapper = mountPage();
        await wrapper.find('[data-testid="cancel-button"]').trigger('click');
        await wrapper.find('[data-testid="confirm-cancel"]').trigger('click');
        await flushPromises();

        expect(cancelMutation.mutateAsync).toHaveBeenCalledWith({ id: 7, version: 3 });
    });

    it('maps a version conflict (409) to a friendly message', async () => {
        cancelMutation.mutateAsync.mockRejectedValue({
            isAxiosError: true,
            response: { data: { error: 'version_conflict' } },
        });
        listState.appointments.value = [appointment({ id: 7, status: 'CONFIRMED' })];

        const wrapper = mountPage();
        await wrapper.find('[data-testid="cancel-button"]').trigger('click');
        await wrapper.find('[data-testid="confirm-cancel"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('Booking sudah berubah');
    });
});
