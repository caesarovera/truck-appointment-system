import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { Driver, SlotWindow, Truck } from '@/types/api';

// Composables di-mock jadi ref/spy terkontrol (pola repo). Tak ada jaringan/QueryClient.
const fleet = {
    trucks: ref<Truck[]>([]),
    drivers: ref<Driver[]>([]),
    isLoading: ref(false),
    isError: ref(false),
};

const booking = {
    mutateAsync: vi.fn(),
    isPending: ref(false),
};

vi.mock('@/composables/useFleet', () => ({ useFleet: () => fleet }));
vi.mock('@/composables/useBookAppointment', () => ({ useBookAppointment: () => booking }));

import BookingForm from '@/components/BookingForm.vue';

const slotWindow: SlotWindow = {
    id: 5,
    gate_id: 1,
    date: '2026-06-28',
    start_time: '08:00:00',
    end_time: '09:00:00',
    capacity: 10,
    booked_count: 3,
    remaining: 7,
    status: 'OPEN',
};

const mountForm = () => mount(BookingForm, { props: { slotWindow } });

async function fillValidForm(wrapper: ReturnType<typeof mountForm>): Promise<void> {
    const selects = wrapper.findAll('select');
    await selects[0].setValue(1); // truk
    await selects[1].setValue(2); // sopir
    await wrapper.find('input[type="text"]').setValue('MAUU1234567'); // container_no
}

beforeEach(() => {
    fleet.trucks.value = [{ id: 1, plate_no: 'B 9011 XX', status: 'ACTIVE' }];
    fleet.drivers.value = [{ id: 2, name: 'Budi Santoso' }];
    booking.isPending.value = false;
    vi.clearAllMocks();
});

describe('BookingForm', () => {
    it('renders truck and driver options from the fleet', () => {
        const wrapper = mountForm();

        expect(wrapper.text()).toContain('B 9011 XX');
        expect(wrapper.text()).toContain('Budi Santoso');
    });

    it('blocks submit until a truck and driver are chosen', async () => {
        const wrapper = mountForm();

        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(booking.mutateAsync).not.toHaveBeenCalled();
        expect(wrapper.find('[role="alert"]').text()).toContain('Pilih truk & sopir');
    });

    it('books and emits "booked" with the result on success', async () => {
        booking.mutateAsync.mockResolvedValue({
            id: 9,
            booking_code: 'TAS-XYZ12345',
            status: 'CONFIRMED',
            move_type: 'DELIVERY',
        });

        const wrapper = mountForm();
        await fillValidForm(wrapper);
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(booking.mutateAsync).toHaveBeenCalledTimes(1);
        const arg = booking.mutateAsync.mock.calls[0][0];
        expect(arg.payload).toMatchObject({
            slot_window_id: 5,
            truck_id: 1,
            driver_id: 2,
            move_type: 'DELIVERY',
            container_no: 'MAUU1234567',
        });
        expect(typeof arg.idempotencyKey).toBe('string');
        expect(arg.idempotencyKey.length).toBeGreaterThan(0);

        expect(wrapper.emitted('booked')?.[0]?.[0]).toMatchObject({ booking_code: 'TAS-XYZ12345' });
    });

    it('maps a 409 slot_unavailable error to a friendly message', async () => {
        booking.mutateAsync.mockRejectedValue({
            isAxiosError: true,
            response: { data: { error: 'slot_unavailable' } },
        });

        const wrapper = mountForm();
        await fillValidForm(wrapper);
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('Slot sudah penuh');
        expect(wrapper.emitted('booked')).toBeUndefined();
    });
});
