import { describe, expect, it } from 'vitest';
import { AxiosError, AxiosHeaders } from 'axios';
import { QueryClient } from '@tanstack/vue-query';
import { shouldRetryQuery, vueQueryOptions } from '@/api/client';

function httpError(status: number): AxiosError {
    const config = { headers: new AxiosHeaders() };
    return new AxiosError('x', 'ERR_BAD_RESPONSE', config, null, { status, statusText: '', headers: {}, config, data: {} });
}

describe('shouldRetryQuery', () => {
    it.each([400, 401, 403, 404, 409, 422, 429])('never retries a %i (the answer will not change)', (status) => {
        expect(shouldRetryQuery(0, httpError(status))).toBe(false);
    });

    it.each([408, 500, 502, 503])('retries a %i up to 3 times like the TanStack default', (status) => {
        expect(shouldRetryQuery(0, httpError(status))).toBe(true);
        expect(shouldRetryQuery(2, httpError(status))).toBe(true);
        expect(shouldRetryQuery(3, httpError(status))).toBe(false);
    });

    it('retries a network error (no response) and a non-axios error', () => {
        expect(shouldRetryQuery(0, new AxiosError('Network Error', 'ERR_NETWORK'))).toBe(true);
        expect(shouldRetryQuery(0, new Error('boom'))).toBe(true);
    });
});

describe('vueQueryOptions', () => {
    it('makes every query of a client built from it use shouldRetryQuery', () => {
        const client = new QueryClient(vueQueryOptions.queryClientConfig);

        expect(client.getDefaultOptions().queries?.retry).toBe(shouldRetryQuery);
    });
});
