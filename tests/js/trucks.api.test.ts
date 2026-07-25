import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { api } from '@/api/client';
import { createTruck, deleteTruck, fetchMyTrucks, updateTruck } from '@/api/trucks';

// Isolasi dari jaringan: verifikasi kontrak request + unwrap `data`.
vi.mock('@/api/client', () => ({
    api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

beforeEach(() => vi.clearAllMocks());

describe('fetchMyTrucks', () => {
    it('GETs /me/trucks and unwraps the data array', async () => {
        (api.get as Mock).mockResolvedValue({ data: { data: [{ id: 1 }, { id: 2 }] } });

        const result = await fetchMyTrucks();

        expect(api.get).toHaveBeenCalledWith('/me/trucks');
        expect(result).toEqual([{ id: 1 }, { id: 2 }]);
    });
});

describe('createTruck', () => {
    it('POSTs the payload and unwraps the created truck', async () => {
        (api.post as Mock).mockResolvedValue({ data: { data: { id: 5, plate_no: 'B 1 X', status: 'ACTIVE' } } });

        const result = await createTruck({ plate_no: 'B 1 X', status: 'ACTIVE' });

        expect(api.post).toHaveBeenCalledWith('/me/trucks', { plate_no: 'B 1 X', status: 'ACTIVE' });
        expect(result.id).toBe(5);
    });
});

describe('updateTruck', () => {
    it('PATCHes /me/trucks/{id} and unwraps the updated truck', async () => {
        (api.patch as Mock).mockResolvedValue({ data: { data: { id: 7, plate_no: 'B 2 Y', status: 'INACTIVE' } } });

        const result = await updateTruck(7, { plate_no: 'B 2 Y', status: 'INACTIVE' });

        expect(api.patch).toHaveBeenCalledWith('/me/trucks/7', { plate_no: 'B 2 Y', status: 'INACTIVE' });
        expect(result.status).toBe('INACTIVE');
    });
});

describe('deleteTruck', () => {
    it('DELETEs /me/trucks/{id}', async () => {
        (api.delete as Mock).mockResolvedValue({});

        await deleteTruck(9);

        expect(api.delete).toHaveBeenCalledWith('/me/trucks/9');
    });
});
