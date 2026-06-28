import { api } from '@/api/client';
import type { Fleet } from '@/types/api';

/** Armada (truk + sopir) milik company transporter — GET /me/fleet. */
export async function fetchFleet(): Promise<Fleet> {
    const { data } = await api.get<{ data: Fleet }>('/me/fleet');

    return data.data;
}
