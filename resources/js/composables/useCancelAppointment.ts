import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { cancelAppointment } from '@/api/appointments';

interface CancelVars {
    id: number;
    version: number;
}

/**
 * Batalkan booking. Sukses → segarkan daftar booking & ketersediaan slot
 * (kuota kembali).
 */
export function useCancelAppointment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (vars: CancelVars) => cancelAppointment(vars.id, vars.version),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['me-appointments'] });
            void queryClient.invalidateQueries({ queryKey: ['slots-availability'] });
        },
    });
}
