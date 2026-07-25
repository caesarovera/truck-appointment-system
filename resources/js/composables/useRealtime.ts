import { onUnmounted, watch, type Ref } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { getEcho } from '@/echo';

/**
 * Ketersediaan slot suatu gate berubah di server (booking/cancel/reschedule/
 * no-show) → server menyiarkan `slot.availability.changed` ke channel privat
 * `slot.{gateId}`. Kita TIDAK menulis payload broadcast (yang sengaja subset)
 * ke cache; cukup INVALIDATE query availability supaya TanStack refetch —
 * API tetap satu-satunya sumber kebenaran bentuk data.
 *
 * Subscribe mengikuti gate terpilih (reaktif): pindah gate → tinggalkan channel
 * lama, gabung yang baru; komponen unmount → tinggalkan (cegah channel bocor).
 * Bila Echo tak aktif (Reverb tak dikonfigurasi), semua jadi no-op.
 */
export function useSlotRealtime(gate: Ref<number | null>): void {
    const queryClient = useQueryClient();
    let joined: number | null = null;

    function leave(): void {
        const echo = getEcho();
        if (echo && joined !== null) {
            echo.leave(`slot.${joined}`);
        }
        joined = null;
    }

    function join(gateId: number): void {
        const echo = getEcho();
        if (!echo) {
            return;
        }
        // `.` di depan = nama event kustom (broadcastAs), bukan kelas PHP.
        echo.private(`slot.${gateId}`).listen('.slot.availability.changed', () => {
            void queryClient.invalidateQueries({ queryKey: ['slots-availability'] });
        });
        joined = gateId;
    }

    watch(
        gate,
        (value) => {
            leave();
            if (typeof value === 'number' && value > 0) {
                join(value);
            }
        },
        { immediate: true },
    );

    onUnmounted(leave);
}

/**
 * Antrian gate satu terminal bergerak (gate-in/out) → server menyiarkan
 * `gate.queue.updated` ke channel privat `gate.queue.{terminalId}`. Invalidate
 * query antrian gate. Terminal berasal dari user yang login (auth.user).
 */
export function useGateQueueRealtime(terminalId: Ref<number | null>): void {
    const queryClient = useQueryClient();
    let joined: number | null = null;

    function leave(): void {
        const echo = getEcho();
        if (echo && joined !== null) {
            echo.leave(`gate.queue.${joined}`);
        }
        joined = null;
    }

    function join(id: number): void {
        const echo = getEcho();
        if (!echo) {
            return;
        }
        echo.private(`gate.queue.${id}`).listen('.gate.queue.updated', () => {
            void queryClient.invalidateQueries({ queryKey: ['gate-queue'] });
        });
        joined = id;
    }

    watch(
        terminalId,
        (value) => {
            leave();
            if (typeof value === 'number' && value > 0) {
                join(value);
            }
        },
        { immediate: true },
    );

    onUnmounted(leave);
}
