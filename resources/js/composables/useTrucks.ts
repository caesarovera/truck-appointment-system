import { computed } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { createTruck, deleteTruck, fetchMyTrucks, updateTruck } from '@/api/trucks';
import type { Truck, TruckPayload } from '@/types/api';

/**
 * CRUD armada truk milik transporter. Semua mutasi meng-invalidate `me-trucks`
 * (halaman ini) DAN `me-fleet` (dropdown truk di form booking ikut segar).
 */
export function useMyTrucks() {
    const client = useQueryClient();
    const query = useQuery({ queryKey: ['me-trucks'], queryFn: fetchMyTrucks });

    const invalidate = (): void => {
        void client.invalidateQueries({ queryKey: ['me-trucks'] });
        void client.invalidateQueries({ queryKey: ['me-fleet'] });
    };

    const create = useMutation({ mutationFn: createTruck, onSuccess: invalidate });

    const update = useMutation({
        mutationFn: ({ id, ...payload }: { id: number } & TruckPayload) => updateTruck(id, payload),
        onSuccess: invalidate,
    });

    const remove = useMutation({ mutationFn: deleteTruck, onSuccess: invalidate });

    return {
        trucks: computed<Truck[]>(() => query.data.value ?? []),
        isLoading: query.isLoading,
        isError: query.isError,
        create,
        update,
        remove,
    };
}
