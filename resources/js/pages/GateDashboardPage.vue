<script setup lang="ts">
import { computed, ref } from 'vue';

import { isAxiosError } from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useGateQueue, useGateIn, useGateOut } from '@/composables/useGateQueue';
import SkeletonRows from '@/components/SkeletonRows.vue';
import ConfirmButton from '@/components/ConfirmButton.vue';
import { useGateQueueRealtime } from '@/composables/useRealtime';
import type { Appointment } from '@/types/api';

const auth = useAuthStore();
const { appointments, isLoading, isError } = useGateQueue();
const gateInMutation = useGateIn();
const gateOutMutation = useGateOut();

// Antrian live: gate-in/out di terminal ini (channel gate.queue.{terminalId})
// memicu refetch. Terminal diambil dari user petugas yang login.
useGateQueueRealtime(computed(() => auth.user?.terminal_id ?? null));

const error = ref<string | null>(null);
// Aksi gate-in/out ireversibel di lapangan → konfirmasi inline dulu, per baris.
const confirmingId = ref<number | null>(null);

// Urut kronologis berdasar jam mulai window.
const sorted = computed(() =>
    [...appointments.value].sort((a, b) =>
        (a.slot_window?.start_time ?? '').localeCompare(b.slot_window?.start_time ?? ''),
    ),
);

async function onGateIn(a: Appointment): Promise<void> {
    error.value = null;
    try {
        await gateInMutation.mutateAsync(a.id);
    } catch (e) {
        error.value = extractError(e);
    } finally {
        confirmingId.value = null;
    }
}

async function onGateOut(a: Appointment): Promise<void> {
    error.value = null;
    try {
        await gateOutMutation.mutateAsync(a.id);
    } catch (e) {
        error.value = extractError(e);
    } finally {
        confirmingId.value = null;
    }
}

function extractError(e: unknown): string {
    if (isAxiosError(e)) {
        const data = e.response?.data as { error?: string; message?: string } | undefined;
        if (data?.error === 'invalid_state') return 'Status tidak sesuai — muat ulang antrian.';
        return data?.message ?? 'Gagal memproses gate. Coba lagi.';
    }
    return 'Terjadi kesalahan. Coba lagi.';
}

const busy = computed(() => gateInMutation.isPending.value || gateOutMutation.isPending.value);
</script>

<template>
    <div class="min-h-screen bg-sand-50">
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h1 class="font-semibold text-harbor-900 text-lg">Dashboard Gate</h1>
        </header>

        <main class="p-6 space-y-4">
            <p v-if="error" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">{{ error }}</p>

            <SkeletonRows v-if="isLoading" :rows="4" label="Memuat antrian…" />

            <p v-else-if="isError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">
                Gagal memuat antrian. Coba lagi.
            </p>

            <p v-else-if="sorted.length === 0" class="text-sm text-gray-500">Tidak ada truk di antrian.</p>

            <ul v-else class="space-y-3" data-testid="queue-list">
                <li
                    v-for="a in sorted"
                    :key="a.id"
                    class="bg-white rounded-lg border p-4 flex items-center justify-between gap-4"
                    data-testid="queue-row"
                >
                    <div class="space-y-1">
                        <p class="font-medium text-gray-900">
                            <span v-if="a.slot_window">
                                {{ a.slot_window.start_time.slice(0, 5) }}–{{ a.slot_window.end_time.slice(0, 5) }}
                            </span>
                            <span v-if="a.slot_window?.gate" class="text-gray-500 font-normal"> · {{ a.slot_window.gate.name }}</span>
                        </p>
                        <p class="text-sm text-gray-600">
                            {{ a.booking_code }} · {{ a.move_type }}
                            <template v-if="a.truck"> · {{ a.truck.plate_no }}</template>
                            <template v-if="a.containers[0]"> · {{ a.containers[0].container_no }}</template>
                        </p>
                    </div>

                    <ConfirmButton
                        v-if="a.status === 'CONFIRMED'"
                        :open="confirmingId === a.id"
                        :pending="gateInMutation.isPending.value"
                        :disabled="busy && confirmingId !== a.id"
                        variant="success"
                        :outline="false"
                        size="md"
                        label="Gate In"
                        confirm-label="Konfirmasi truk masuk?"
                        testid="gate-in"
                        @update:open="(v) => (confirmingId = v ? a.id : null)"
                        @confirm="onGateIn(a)"
                    />
                    <ConfirmButton
                        v-else-if="a.status === 'IN_PROGRESS'"
                        :open="confirmingId === a.id"
                        :pending="gateOutMutation.isPending.value"
                        :disabled="busy && confirmingId !== a.id"
                        variant="primary"
                        :outline="false"
                        size="md"
                        label="Gate Out"
                        confirm-label="Konfirmasi truk keluar?"
                        testid="gate-out"
                        @update:open="(v) => (confirmingId = v ? a.id : null)"
                        @confirm="onGateOut(a)"
                    />
                </li>
            </ul>
        </main>
    </div>
</template>
