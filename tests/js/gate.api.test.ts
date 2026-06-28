import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { api } from '@/api/client';
import { fetchGateQueue, gateIn, gateOut } from '@/api/gate';

vi.mock('@/api/client', () => ({ api: { get: vi.fn(), post: vi.fn() } }));

beforeEach(() => vi.clearAllMocks());

describe('fetchGateQueue', () => {
    it('unwraps the data array (no date param by default)', async () => {
        (api.get as Mock).mockResolvedValue({ data: { data: [{ id: 1 }] } });

        const result = await fetchGateQueue();

        expect(api.get).toHaveBeenCalledWith('/gate/queue', { params: {} });
        expect(result).toEqual([{ id: 1 }]);
    });
});

describe('gateIn / gateOut', () => {
    it('posts gate-in with an Idempotency-Key header', async () => {
        (api.post as Mock).mockResolvedValue({ data: { data: { id: 5, status: 'IN_PROGRESS' } } });

        const result = await gateIn(5);

        expect(api.post).toHaveBeenCalledTimes(1);
        const [url, body, config] = (api.post as Mock).mock.calls[0];
        expect(url).toBe('/appointments/5/gate-in');
        expect(body).toEqual({});
        expect(config.headers['Idempotency-Key']).toBeTruthy();
        expect(result.status).toBe('IN_PROGRESS');
    });

    it('posts gate-out to the right endpoint', async () => {
        (api.post as Mock).mockResolvedValue({ data: { data: { id: 5, status: 'COMPLETED' } } });

        await gateOut(5);

        expect((api.post as Mock).mock.calls[0][0]).toBe('/appointments/5/gate-out');
    });
});
