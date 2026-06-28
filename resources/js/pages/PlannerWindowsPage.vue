<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { isAxiosError } from 'axios';
import { useGates } from '@/composables/useGates';
import { useUtilization, useOpenSlotWindow, useCloseSlotWindow } from '@/composables/usePlannerWindows';

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

const gate = ref<number | null>(null);
const date = ref<string>(today());

const { gates, isLoading: gatesLoading } = useGates();
const { windows, summary, isLoading, isError, enabled } = useUtilization(gate, date);
const openMutation = useOpenSlotWindow();
const closeMutation = useCloseSlotWindow();

// Form buka window.
const startTime = ref('');
const endTime = ref('');
const capacity = ref<number | null>(null);
const formError = ref<string | null>(null);
const notice = ref<string | null>(null);

function withSeconds(t: string): string {
    return t.length === 5 ? `${t}:00` : t;
}

async function openWindow(): Promise<void> {
    formError.value = null;
    notice.value = null;

    if (gate.value === null || startTime.value === '' || endTime.value === '' || capacity.value === null) {
        formError.value = 'Lengkapi gate, jam, dan kapasitas.';
        return;
    }

    try {
        await openMutation.mutateAsync({
            gate: gate.value,
            date: date.value,
            start_time: withSeconds(startTime.value),
            end_time: withSeconds(endTime.value),
            capacity: capacity.value,
        });
        notice.value = 'Window dibuka.';
        startTime.value = '';
        endTime.value = '';
        capacity.value = null;
    } catch (e) {
        formError.value = extractError(e);
    }
}

async function closeWindow(id: number): Promise<void> {
    notice.value = null;
    formError.value = null;
    try {
        await closeMutation.mutateAsync(id);
        notice.value = 'Window ditutup.';
    } catch (e) {
        formError.value = extractError(e);
    }
}

function extractError(e: unknown): string {
    if (isAxiosError(e)) {
        const data = e.response?.data as { error?: string; message?: string } | undefined;
        if (data?.error === 'duplicate_slot_window') return 'Window untuk gate/tanggal/jam itu sudah ada.';
        return data?.message ?? 'Operasi gagal. Periksa input lalu coba lagi.';
    }
    return 'Terjadi kesalahan. Coba lagi.';
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
            <h1 class="font-semibold text-gray-900">Kelola Slot Window</h1>
            <RouterLink to="/" class="text-sm text-gray-600 hover:text-gray-900">← Dashboard</RouterLink>
        </header>

        <main class="p-6 space-y-6">
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

            <p v-if="notice" role="status" class="text-sm text-green-700 bg-green-50 rounded-md p-2">{{ notice }}</p>
            <p v-if="formError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-2">{{ formError }}</p>

            <!-- Form buka window -->
            <form class="bg-white rounded-lg border p-4 flex flex-wrap items-end gap-3" @submit.prevent="openWindow">
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Mulai</span>
                    <input v-model="startTime" type="time" class="rounded-md border border-gray-300 px-3 py-2" />
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Selesai</span>
                    <input v-model="endTime" type="time" class="rounded-md border border-gray-300 px-3 py-2" />
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Kapasitas</span>
                    <input v-model.number="capacity" type="number" min="1" class="w-28 rounded-md border border-gray-300 px-3 py-2" />
                </label>
                <button
                    type="submit"
                    :disabled="openMutation.isPending.value"
                    class="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                    data-testid="open-window"
                >
                    {{ openMutation.isPending.value ? 'Memproses…' : 'Buka window' }}
                </button>
            </form>

            <!-- Daftar window -->
            <p v-if="!enabled" class="text-sm text-gray-500">Pilih gate untuk melihat window.</p>
            <p v-else-if="isLoading" class="text-sm text-gray-500">Memuat window…</p>
            <p v-else-if="isError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">
                Gagal memuat window. Coba lagi.
            </p>
            <p v-else-if="windows.length === 0" class="text-sm text-gray-500">Belum ada window untuk gate &amp; tanggal ini.</p>

            <template v-else>
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
                                Terisi {{ w.booked_count }}/{{ w.capacity }} · sisa {{ w.remaining }}
                                <template v-if="w.no_show !== undefined"> · no-show {{ w.no_show }}</template>
                            </p>
                        </div>
                        <button
                            v-if="w.status === 'OPEN'"
                            type="button"
                            :disabled="closeMutation.isPending.value"
                            class="rounded-md border border-red-300 text-red-700 px-3 py-1 text-sm hover:bg-red-50 disabled:opacity-50"
                            data-testid="close-window"
                            @click="closeWindow(w.id)"
                        >
                            Tutup
                        </button>
                    </li>
                </ul>

                <p v-if="summary" class="text-sm text-gray-600">
                    Total kapasitas {{ summary.capacity }} · terisi {{ summary.booked }} · selesai {{ summary.completed }} ·
                    no-show {{ summary.no_show }}
                </p>
            </template>
        </main>
    </div>
</template>
