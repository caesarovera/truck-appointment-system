import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { api } from '@/api/client';
import { closeSlotWindow, fetchAvailability, fetchUtilization, openSlotWindow } from '@/api/slots';
import type { OpenWindowPayload } from '@/types/api';

// Isolasi dari jaringan: hanya verifikasi kontrak request + unwrap respons.
vi.mock('@/api/client', () => ({ api: { get: vi.fn(), post: vi.fn() } }));

beforeEach(() => vi.clearAllMocks());

describe('fetchAvailability', () => {
    it('requests the gate and date, then unwraps the data array', async () => {
        (api.get as Mock).mockResolvedValue({ data: { data: [{ id: 1 }, { id: 2 }] } });

        const result = await fetchAvailability(1, '2026-06-28');

        expect(api.get).toHaveBeenCalledWith('/slots/availability', {
            params: { gate: 1, date: '2026-06-28' },
        });
        expect(result).toEqual([{ id: 1 }, { id: 2 }]);
    });

    it('omits the date param when none is supplied (server defaults to today)', async () => {
        (api.get as Mock).mockResolvedValue({ data: { data: [] } });

        await fetchAvailability(2);

        expect(api.get).toHaveBeenCalledWith('/slots/availability', { params: { gate: 2 } });
    });
});

describe('fetchUtilization', () => {
    it('returns windows + summary from data/meta', async () => {
        (api.get as Mock).mockResolvedValue({
            data: { data: [{ id: 1 }], meta: { summary: { capacity: 10, booked: 3 } } },
        });

        const result = await fetchUtilization(1, '2026-06-28');

        expect(api.get).toHaveBeenCalledWith('/reports/utilization', { params: { gate: 1, date: '2026-06-28' } });
        expect(result.windows).toEqual([{ id: 1 }]);
        expect(result.summary).toEqual({ capacity: 10, booked: 3 });
    });
});

describe('openSlotWindow / closeSlotWindow', () => {
    it('posts the open payload and unwraps data', async () => {
        const payload: OpenWindowPayload = {
            gate: 1,
            date: '2026-06-29',
            start_time: '08:00:00',
            end_time: '09:00:00',
            capacity: 10,
        };
        (api.post as Mock).mockResolvedValue({ data: { data: { id: 9, status: 'OPEN' } } });

        const result = await openSlotWindow(payload);

        expect(api.post).toHaveBeenCalledWith('/slots', payload);
        expect(result.status).toBe('OPEN');
    });

    it('posts to the close endpoint', async () => {
        (api.post as Mock).mockResolvedValue({ data: { data: { id: 9, status: 'CLOSED' } } });

        await closeSlotWindow(9);

        expect(api.post).toHaveBeenCalledWith('/slots/9/close', {});
    });
});
