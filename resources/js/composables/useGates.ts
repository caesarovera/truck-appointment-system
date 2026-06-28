import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { fetchGates } from '@/api/gates';
import type { Gate } from '@/types/api';

/** Daftar gate untuk dropdown (jarang berubah → cache lebih lama). */
export function useGates() {
    const query = useQuery({
        queryKey: ['gates'],
        queryFn: () => fetchGates(),
        staleTime: 5 * 60 * 1000,
    });

    return {
        gates: computed<Gate[]>(() => query.data.value ?? []),
        isLoading: query.isLoading,
        isError: query.isError,
    };
}
