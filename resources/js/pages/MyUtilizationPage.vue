<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useGates } from '@/composables/useGates';
import { useMyUtilization } from '@/composables/useMyUtilization';

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

const gate = ref<number | null>(null);
const date = ref<string>(today());

const { gates, isLoading: gatesLoading } = useGates();
const { windows, summary, isLoading, isError, enabled } = useMyUtilization(gate, date);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
            <h1 class="font-semibold text-gray-900">Laporan Booking Perusahaan</h1>
            <RouterLink to="/" class="text-sm text-gray-600 hover:text-gray-900">← Dashboard</RouterLink>
        </header>

        <main class="p-6 space-y-6">
            <p class="text-sm text-gray-600">
                Performa appointment <strong>perusahaan Anda sendiri</strong> per window
                (selesai / no-show / batal / aktif). Kapasitas &amp; terisi adalah angka gate keseluruhan.
            </p>

            <div class="flex flex-wrap items-end gap-4">
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Gate</span>
                    <select
                        v-model.number="gate"
                        :disabled="gatesLoading"
                        class="w-48 rounded-md border border-gray-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
                        class="rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </label>
            </div>

            <p v-if="!enabled" class="text-sm text-gray-500">Pilih gate untuk melihat laporan.</p>
            <p v-else-if="isLoading" class="text-sm text-gray-500">Memuat laporan…</p>
            <p v-else-if="isError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">
                Gagal memuat laporan. Coba lagi.
            </p>
            <p v-else-if="windows.length === 0" class="text-sm text-gray-500">
                Tidak ada window untuk gate &amp; tanggal ini.
            </p>

            <template v-else>
                <div
                    v-if="summary"
                    class="bg-white rounded-lg border p-4 flex flex-wrap gap-6 text-sm"
                    data-testid="summary"
                >
                    <span>Selesai: <strong>{{ summary.completed }}</strong></span>
                    <span>No-show: <strong>{{ summary.no_show }}</strong></span>
                    <span>Batal: <strong>{{ summary.cancelled }}</strong></span>
                    <span>Aktif: <strong>{{ summary.active }}</strong></span>
                </div>

                <ul class="space-y-2" data-testid="window-list">
                    <li
                        v-for="w in windows"
                        :key="w.id"
                        class="bg-white rounded-lg border p-4 flex items-center justify-between gap-4"
                        data-testid="window-row"
                    >
                        <div class="space-y-1">
                            <p class="font-medium text-gray-900">
                                {{ w.start_time.slice(0, 5) }}–{{ w.end_time.slice(0, 5) }}
                                <span
                                    class="ml-2 text-xs rounded-full px-2 py-0.5"
                                    :class="w.status === 'OPEN' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'"
                                >{{ w.status }}</span>
                            </p>
                            <p class="text-sm text-gray-600">
                                Milik Anda: selesai {{ w.completed ?? 0 }} · no-show {{ w.no_show ?? 0 }}
                                · batal {{ w.cancelled ?? 0 }} · aktif {{ w.active ?? 0 }}
                            </p>
                            <p class="text-xs text-gray-500">Gate: terisi {{ w.booked_count }}/{{ w.capacity }}</p>
                        </div>
                    </li>
                </ul>
            </template>
        </main>
    </div>
</template>
