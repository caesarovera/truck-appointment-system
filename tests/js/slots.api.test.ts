import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { api } from '@/api/client';
import { fetchAvailability } from '@/api/slots';

// Isolasi dari jaringan: hanya verifikasi kontrak request + unwrap respons.
vi.mock('@/api/client', () => ({ api: { get: vi.fn() } }));

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
