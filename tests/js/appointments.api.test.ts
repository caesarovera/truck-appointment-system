import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { api } from '@/api/client';
import { bookAppointment } from '@/api/appointments';
import type { BookAppointmentPayload } from '@/types/api';

vi.mock('@/api/client', () => ({ api: { post: vi.fn() } }));

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
