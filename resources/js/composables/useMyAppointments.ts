import { computed, type Ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { fetchMyAppointments } from '@/api/appointments';
import type { Appointment } from '@/types/api';

/** Daftar booking transporter; key ikut filter status reaktif. */
export function useMyAppointments(status: Ref<string>) {
    const query = useQuery({
        queryKey: ['me-appointments', status],
        queryFn: () => fetchMyAppointments(status.value !== '' ? status.value : undefined),
    });

    return {
        appointments: computed<Appointment[]>(() => query.data.value ?? []),
        isLoading: query.isLoading,
        isError: query.isError,
    };
}
