import { computed } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { fetchGateQueue, gateIn, gateOut } from '@/api/gate';
import type { Appointment } from '@/types/api';

/** Antrian gate hari ini (di terminal officer). */
export function useGateQueue() {
    const query = useQuery({
        queryKey: ['gate-queue'],
        queryFn: () => fetchGateQueue(),
    });

    return {
        appointments: computed<Appointment[]>(() => query.data.value ?? []),
        isLoading: query.isLoading,
        isError: query.isError,
    };
}

/** Gate-in: sukses → segarkan antrian (baris pindah ke IN_PROGRESS). */
export function useGateIn() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => gateIn(id),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['gate-queue'] });
        },
    });
}

/** Gate-out: sukses → segarkan antrian (baris keluar = COMPLETED). */
export function useGateOut() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => gateOut(id),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['gate-queue'] });
        },
    });
}
