import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { api } from '@/api/client';
import { bookAppointment, cancelAppointment, fetchMyAppointments } from '@/api/appointments';
import type { BookAppointmentPayload } from '@/types/api';

vi.mock('@/api/client', () => ({ api: { post: vi.fn(), get: vi.fn() } }));

beforeEach(() => vi.clearAllMocks());

const payload: BookAppointmentPayload = {
    slot_window_id: 5,
    truck_id: 1,
    driver_id: 2,
    move_type: 'DELIVERY',
    container_no: 'MAUU1234567',
};

describe('bookAppointment', () => {
    it('posts the payload with an Idempotency-Key header and unwraps data', async () => {
        (api.post as Mock).mockResolvedValue({
            data: { data: { id: 9, booking_code: 'TAS-ABCD1234', status: 'CONFIRMED', move_type: 'DELIVERY' } },
        });

        const result = await bookAppointment(payload, 'key-123');

        expect(api.post).toHaveBeenCalledWith('/appointments', payload, {
            headers: { 'Idempotency-Key': 'key-123' },
        });
        expect(result.booking_code).toBe('TAS-ABCD1234');
    });
});

describe('fetchMyAppointments', () => {
    it('unwraps data and omits the status param when not filtered', async () => {
        (api.get as Mock).mockResolvedValue({ data: { data: [{ id: 1 }] } });

        const result = await fetchMyAppointments();

        expect(api.get).toHaveBeenCalledWith('/me/appointments', { params: {} });
        expect(result).toEqual([{ id: 1 }]);
    });

    it('passes the status filter when given', async () => {
        (api.get as Mock).mockResolvedValue({ data: { data: [] } });

        await fetchMyAppointments('CANCELLED');

        expect(api.get).toHaveBeenCalledWith('/me/appointments', { params: { status: 'CANCELLED' } });
    });
});

describe('cancelAppointment', () => {
    it('posts the version for optimistic locking and unwraps data', async () => {
        (api.post as Mock).mockResolvedValue({ data: { data: { id: 3, status: 'CANCELLED' } } });

        const result = await cancelAppointment(3, 2);

        expect(api.post).toHaveBeenCalledWith('/appointments/3/cancel', { version: 2 });
        expect(result.status).toBe('CANCELLED');
    });
});
