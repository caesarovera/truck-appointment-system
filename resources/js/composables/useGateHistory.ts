import { computed, type Ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { fetchGateHistory } from '@/api/appointments';
import type { Appointment } from '@/types/api';

/** Riwayat gate-in/out (planner/admin); key ikut gate+date reaktif. */
export function useGateHistory(gate: Ref<number | null>, date: Ref<string>) {
    const enabled = computed(() => typeof gate.value === 'number' && gate.value > 0);

    const query = useQuery({
        queryKey: ['gate-history', gate, date],
        queryFn: () => fetchGateHistory(gate.value as number, date.value),
        enabled,
    });

    return {
        appointments: computed<Appointment[]>(() => query.data.value ?? []),
        isLoading: query.isLoading,
        isError: query.isError,
        enabled,
    };
}
