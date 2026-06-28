import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { fetchTodaySchedule } from '@/api/appointments';
import type { Appointment } from '@/types/api';

/** Jadwal hari-H sopir. */
export function useTodaySchedule() {
    const query = useQuery({
        queryKey: ['me-today'],
        queryFn: fetchTodaySchedule,
    });

    return {
        appointments: computed<Appointment[]>(() => query.data.value ?? []),
        isLoading: query.isLoading,
        isError: query.isError,
    };
}
