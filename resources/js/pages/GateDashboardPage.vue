<script setup lang="ts">
import { computed, ref } from 'vue';

import { isAxiosError } from 'axios';
import { useGateQueue, useGateIn, useGateOut } from '@/composables/useGateQueue';
import type { Appointment } from '@/types/api';

const { appointments, isLoading, isError } = useGateQueue();
const gateInMutation = useGateIn();
const gateOutMutation = useGateOut();

const error = ref<string | null>(null);

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
    }
}

async function onGateOut(a: Appointment): Promise<void> {
    error.value = null;
    try {
        await gateOutMutation.mutateAsync(a.id);
    } catch (e) {
        error.value = extractError(e);
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
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
            <h1 class="font-semibold text-gray-900">Dashboard Gate</h1>
        </header>

        <main class="p-6 space-y-4">
            <p v-if="error" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">{{ error }}</p>

            <p v-if="isLoading" class="text-sm text-gray-500">Memuat antrian…</p>

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

                    <button
                        v-if="a.status === 'CONFIRMED'"
                        type="button"
                        :disabled="busy"
                        class="rounded-md bg-green-600 text-white px-4 py-2 text-sm font-medium hover:bg-green-700 disabled:opacity-50"
                        data-testid="gate-in"
                        @click="onGateIn(a)"
                    >
                        Gate In
                    </button>
                    <button
                        v-else-if="a.status === 'IN_PROGRESS'"
                        type="button"
                        :disabled="busy"
                        class="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                        data-testid="gate-out"
                        @click="onGateOut(a)"
                    >
                        Gate Out
                    </button>
                </li>
            </ul>
        </main>
    </div>
</template>
