<script setup lang="ts">
import { computed, ref } from 'vue';

import { useGates } from '@/composables/useGates';
import { useGateHistory } from '@/composables/useGateHistory';
import SkeletonRows from '@/components/SkeletonRows.vue';
import type { Appointment } from '@/types/api';

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

const gate = ref<number | null>(null);
const date = ref<string>(today());

const { gates, isLoading: gatesLoading } = useGates();
const { appointments, isLoading, isError, enabled } = useGateHistory(gate, date);

// Urutan diserahkan ke klien (repo sengaja tak sort kolom relasi — lihat
// AppointmentRepository::gateHistoryForGate). Gate-in duluan tampil lebih dulu.
const sorted = computed<Appointment[]>(() =>
    [...appointments.value].sort((a, b) => (a.gate_in_at ?? '').localeCompare(b.gate_in_at ?? '')),
);

function formatTime(iso: string | null | undefined): string {
    if (iso === null || iso === undefined) return '—';
    return iso.slice(11, 16); // "2026-08-13T09:00:00.000000Z" → "09:00"
}
</script>

<template>
    <div class="min-h-screen bg-sand-50">
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h1 class="font-semibold text-harbor-900 text-lg">Riwayat Gate</h1>
        </header>

        <main class="p-6 space-y-6">
            <div class="flex flex-wrap items-end gap-4">
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Gate</span>
                    <select
                        v-model.number="gate"
                        :disabled="gatesLoading"
                        class="w-48 rounded-md border border-gray-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-harbor-500"
                    >
                        <option :value="null" disabled>Pilih gate</option>
                        <option v-for="g in gates" :key="g.id" :value="g.id">{{ g.name }}</option>
                    </select>
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Tanggal</span>
                    <input
                        v-model="date"
                        type="date"
                        class="rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-harbor-500"
                    />
                </label>
            </div>

            <p v-if="!enabled" class="text-sm text-gray-500">Pilih gate untuk melihat riwayat.</p>
            <SkeletonRows v-else-if="isLoading" :rows="4" label="Memuat riwayat…" />
            <p v-else-if="isError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">
                Gagal memuat riwayat gate. Coba lagi.
            </p>
            <p v-else-if="sorted.length === 0" class="text-sm text-gray-500">
                Belum ada truk yang gate-in untuk gate &amp; tanggal ini.
            </p>

            <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="history-list">
                <li
                    v-for="a in sorted"
                    :key="a.id"
                    class="bg-white rounded-lg border p-4 space-y-2"
                    data-testid="history-row"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-900">{{ a.booking_code }}</span>
                        <span class="text-xs rounded-full px-2 py-0.5 bg-harbor-50 text-harbor-700">{{ a.status }}</span>
                    </div>
                    <p class="text-sm text-gray-600">
                        <template v-if="a.truck"> {{ a.truck.plate_no }} </template>
                        <template v-if="a.company"> · {{ a.company.name }} </template>
                    </p>
                    <p class="text-sm text-gray-600">
                        Gate in <strong>{{ formatTime(a.gate_in_at) }}</strong>
                        · Gate out <strong>{{ formatTime(a.gate_out_at) }}</strong>
                        <template v-if="a.dwell_minutes != null"> · {{ a.dwell_minutes }} menit</template>
                    </p>
                </li>
            </ul>
        </main>
    </div>
</template>
