import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { fetchFleet } from '@/api/fleet';
import type { Driver, Truck } from '@/types/api';

/** Armada transporter untuk form booking (jarang berubah → cache lebih lama). */
export function useFleet() {
    const query = useQuery({
        queryKey: ['me-fleet'],
        queryFn: fetchFleet,
        staleTime: 5 * 60 * 1000,
    });

    return {
        trucks: computed<Truck[]>(() => query.data.value?.trucks ?? []),
        drivers: computed<Driver[]>(() => query.data.value?.drivers ?? []),
        isLoading: query.isLoading,
        isError: query.isError,
    };
}
