import { api } from '@/api/client';
import type {
    OpenWindowPayload,
    SlotAvailabilityResponse,
    SlotUtilization,
    SlotWindow,
    UtilizationSummary,
} from '@/types/api';

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

/**
 * Utilisasi window per gate/tanggal (planner) — semua status, plus ringkasan.
 * GET /reports/utilization.
 */
export async function fetchUtilization(
    gate: number,
    date: string,
): Promise<{ windows: SlotUtilization[]; summary: UtilizationSummary }> {
    const { data } = await api.get<{ data: SlotUtilization[]; meta: { summary: UtilizationSummary } }>(
        '/reports/utilization',
        { params: { gate, date } },
    );

    return { windows: data.data, summary: data.meta.summary };
}

/** Buka window baru (planner) — POST /slots. */
export async function openSlotWindow(payload: OpenWindowPayload): Promise<SlotWindow> {
    const { data } = await api.post<{ data: SlotWindow }>('/slots', payload);

    return data.data;
}

/** Tutup window (planner) — POST /slots/{id}/close, idempoten. */
export async function closeSlotWindow(id: number): Promise<SlotWindow> {
    const { data } = await api.post<{ data: SlotWindow }>(`/slots/${id}/close`, {});

    return data.data;
}
