import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { api } from '@/api/client';
import { fetchGates } from '@/api/gates';

vi.mock('@/api/client', () => ({ api: { get: vi.fn() } }));

beforeEach(() => vi.clearAllMocks());

describe('fetchGates', () => {
    it('unwraps the data array (no terminal filter by default)', async () => {
        (api.get as Mock).mockResolvedValue({ data: { data: [{ id: 1 }, { id: 2 }] } });

        const result = await fetchGates();

        expect(api.get).toHaveBeenCalledWith('/gates', { params: {} });
        expect(result).toEqual([{ id: 1 }, { id: 2 }]);
    });

    it('passes the terminal filter when given', async () => {
        (api.get as Mock).mockResolvedValue({ data: { data: [] } });

        await fetchGates(7);

        expect(api.get).toHaveBeenCalledWith('/gates', { params: { terminal: 7 } });
    });
});
