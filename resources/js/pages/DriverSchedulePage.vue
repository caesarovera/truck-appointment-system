<script setup lang="ts">
import { computed } from 'vue';

import { useTodaySchedule } from '@/composables/useTodaySchedule';

const { appointments, isLoading, isError } = useTodaySchedule();

// Urut kronologis berdasar jam mulai window.
const sorted = computed(() =>
    [...appointments.value].sort((a, b) =>
        (a.slot_window?.start_time ?? '').localeCompare(b.slot_window?.start_time ?? ''),
    ),
);

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
            <h1 class="font-semibold text-gray-900">Jadwal Hari Ini <span class="text-gray-400 font-normal">· {{ today }}</span></h1>
        </header>

        <main class="p-6 space-y-4">
            <p v-if="isLoading" class="text-sm text-gray-500">Memuat jadwal…</p>

            <p v-else-if="isError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">
                Gagal memuat jadwal. Coba lagi.
            </p>

            <p v-else-if="sorted.length === 0" class="text-sm text-gray-500">Tidak ada jadwal untuk hari ini.</p>

            <ul v-else class="space-y-3" data-testid="schedule-list">
                <li
                    v-for="a in sorted"
                    :key="a.id"
                    class="bg-white rounded-lg border p-4 flex items-center justify-between"
                    data-testid="schedule-row"
                >
                    <div class="space-y-1">
                        <p class="font-medium text-gray-900">
                            <span v-if="a.slot_window">
                                {{ a.slot_window.start_time.slice(0, 5) }}–{{ a.slot_window.end_time.slice(0, 5) }}
                            </span>
                            <span v-if="a.slot_window?.gate" class="text-gray-500 font-normal"> · {{ a.slot_window.gate.name }}</span>
                        </p>
                        <p class="text-sm text-gray-600">
                            {{ a.move_type }} · {{ a.booking_code }}
                            <template v-if="a.containers[0]"> · {{ a.containers[0].container_no }}</template>
                        </p>
                    </div>
                    <span class="text-xs rounded-full px-2 py-0.5 bg-gray-100 text-gray-700">{{ a.status }}</span>
                </li>
            </ul>
        </main>
    </div>
</template>
