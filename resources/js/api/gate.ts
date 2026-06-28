import { api } from '@/api/client';
import type { Appointment } from '@/types/api';

/** Antrian gate-officer (CONFIRMED/IN_PROGRESS di terminalnya) — GET /gate/queue. */
export async function fetchGateQueue(date?: string): Promise<Appointment[]> {
    const params: Record<string, string> = {};
    if (date !== undefined && date !== '') {
        params.date = date;
    }

    const { data } = await api.get<{ data: Appointment[] }>('/gate/queue', { params });

    return data.data;
}

function idempotencyKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `idem-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

/** Gate-in (CONFIRMED → IN_PROGRESS). Idempotency-Key untuk anti double-tap. */
export async function gateIn(id: number): Promise<Appointment> {
    const { data } = await api.post<{ data: Appointment }>(`/appointments/${id}/gate-in`, {}, {
        headers: { 'Idempotency-Key': idempotencyKey() },
    });

    return data.data;
}

/** Gate-out (IN_PROGRESS → COMPLETED). */
export async function gateOut(id: number): Promise<Appointment> {
    const { data } = await api.post<{ data: Appointment }>(`/appointments/${id}/gate-out`, {}, {
        headers: { 'Idempotency-Key': idempotencyKey() },
    });

    return data.data;
}
