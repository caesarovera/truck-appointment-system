import { api } from '@/api/client';
import type { SlotAvailabilityResponse, SlotWindow } from '@/types/api';

/**
 * Ketersediaan slot untuk satu gate pada satu tanggal (default: hari ini, diatur
 * server bila `date` dikosongkan). Membuka bungkus `data` → array SlotWindow.
 */
export async function fetchAvailability(gate: number, date?: string): Promise<SlotWindow[]> {
    const params: Record<string, string | number> = { gate };
    if (date !== undefined && date !== '') {
        params.date = date;
    }

    const { data } = await api.get<SlotAvailabilityResponse>('/slots/availability', { params });

    return data.data;
}
