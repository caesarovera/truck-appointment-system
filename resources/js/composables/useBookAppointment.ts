import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { bookAppointment } from '@/api/appointments';
import type { BookAppointmentPayload } from '@/types/api';

interface BookVars {
    payload: BookAppointmentPayload;
    idempotencyKey: string;
}

/**
 * Mutation booking. Sukses → invalidasi cache ketersediaan slot agar sisa kuota
 * langsung ter-refresh di halaman.
 */
export function useBookAppointment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (vars: BookVars) => bookAppointment(vars.payload, vars.idempotencyKey),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['slots-availability'] });
        },
    });
}
