import { api } from '@/api/client';
import type { Gate } from '@/types/api';

/** Daftar gate (referensi dropdown). `terminal` opsional untuk menyaring. */
export async function fetchGates(terminal?: number): Promise<Gate[]> {
    const params: Record<string, number> = {};
    if (terminal !== undefined) {
        params.terminal = terminal;
    }

    const { data } = await api.get<{ data: Gate[] }>('/gates', { params });

    return data.data;
}
