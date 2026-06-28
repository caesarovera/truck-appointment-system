import { computed, type Ref } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { closeSlotWindow, fetchUtilization, openSlotWindow } from '@/api/slots';
import type { SlotUtilization, UtilizationSummary } from '@/types/api';

/** Daftar window + utilisasi per gate/tanggal; key ikut gate+date reaktif. */
export function useUtilization(gate: Ref<number | null>, date: Ref<string>) {
    const enabled = computed(() => typeof gate.value === 'number' && gate.value > 0);

    const query = useQuery({
        queryKey: ['utilization', gate, date],
        queryFn: () => fetchUtilization(gate.value as number, date.value),
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

/** Invalidasi yang dipakai bersama saat window berubah (buka/tutup). */
function invalidate(queryClient: ReturnType<typeof useQueryClient>): void {
    void queryClient.invalidateQueries({ queryKey: ['utilization'] });
    void queryClient.invalidateQueries({ queryKey: ['slots-availability'] });
}

export function useOpenSlotWindow() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: openSlotWindow,
        onSuccess: () => invalidate(queryClient),
    });
}

export function useCloseSlotWindow() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => closeSlotWindow(id),
        onSuccess: () => invalidate(queryClient),
    });
}
