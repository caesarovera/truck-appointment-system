import { computed, type Ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { fetchMyUtilization } from '@/api/slots';
import type { SlotUtilization, UtilizationSummary } from '@/types/api';

/**
 * Utilisasi company sendiri per gate/tanggal (transporter, read-only).
 * Key TERPISAH dari ['utilization'] planner: datanya beda scope (company vs
 * agregat) — tidak boleh saling menimpa di cache TanStack Query.
 */
export function useMyUtilization(gate: Ref<number | null>, date: Ref<string>) {
    const enabled = computed(() => typeof gate.value === 'number' && gate.value > 0);

    const query = useQuery({
        queryKey: ['my-utilization', gate, date],
        queryFn: () => fetchMyUtilization(gate.value as number, date.value),
        enabled,
    });

    return {
        windows: computed<SlotUtilization[]>(() => query.data.value?.windows ?? []),
        summary: computed<UtilizationSummary | null>(() => query.data.value?.summary ?? null),
        isLoading: query.isLoading,
        isError: query.isError,
        enabled,
    };
}
