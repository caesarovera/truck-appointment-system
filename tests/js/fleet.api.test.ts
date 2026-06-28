import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { api } from '@/api/client';
import { fetchFleet } from '@/api/fleet';

vi.mock('@/api/client', () => ({ api: { get: vi.fn() } }));

beforeEach(() => vi.clearAllMocks());

describe('fetchFleet', () => {
    it('unwraps the {data:{trucks,drivers}} payload', async () => {
        const fleet = { trucks: [{ id: 1, plate_no: 'B 1', status: 'ACTIVE' }], drivers: [{ id: 2, name: 'Budi' }] };
        (api.get as Mock).mockResolvedValue({ data: { data: fleet } });

        const result = await fetchFleet();

        expect(api.get).toHaveBeenCalledWith('/me/fleet');
        expect(result).toEqual(fleet);
    });
});
