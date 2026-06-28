import { computed, type Ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { fetchAvailability } from '@/api/slots';
import type { SlotWindow } from '@/types/api';

/**
 * Ketersediaan slot (TanStack Query). Query key ikut `gate`+`date` reaktif →
 * ganti input otomatis refetch. Nonaktif sampai gate valid (>0) supaya tak
 * menembak API dengan parameter kosong.
 */
export function useSlotAvailability(gate: Ref<number | null>, date: Ref<string>) {
    const enabled = computed(() => typeof gate.value === 'number' && gate.value > 0);

    const query = useQuery({
        queryKey: ['slots-availability', gate, date],
        queryFn: () => fetchAvailability(gate.value as number, date.value),
        enabled,
    });

    return {
        windows: computed<SlotWindow[]>(() => query.data.value ?? []),
        isLoading: query.isLoading,
        isFetching: query.isFetching,
        isError: query.isError,
        refetch: query.refetch,
        enabled,
    };
}
