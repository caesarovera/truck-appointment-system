import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { rescheduleAppointment } from '@/api/appointments';

interface RescheduleVars {
    id: number;
    slotWindowId: number;
    version: number;
}

/**
 * Pindahkan booking ke window lain. Sukses → segarkan daftar booking &
 * ketersediaan slot (kuota pindah antar window).
 */
export function useRescheduleAppointment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (vars: RescheduleVars) =>
            rescheduleAppointment(vars.id, vars.slotWindowId, vars.version),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['me-appointments'] });
            void queryClient.invalidateQueries({ queryKey: ['slots-availability'] });
        },
    });
}
