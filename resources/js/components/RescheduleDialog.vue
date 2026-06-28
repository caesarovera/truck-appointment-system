<script setup lang="ts">
import { ref } from 'vue';
import { isAxiosError } from 'axios';
import { useGates } from '@/composables/useGates';
import { useSlotAvailability } from '@/composables/useSlotAvailability';
import { useRescheduleAppointment } from '@/composables/useRescheduleAppointment';
import type { Appointment } from '@/types/api';

const props = defineProps<{ appointment: Appointment }>();
const emit = defineEmits<{ rescheduled: [Appointment]; cancel: [] }>();

// Buka pada gate/tanggal window saat ini supaya konteksnya langsung relevan.
const gate = ref<number | null>(props.appointment.slot_window?.gate_id ?? null);
const date = ref<string>(props.appointment.slot_window?.date ?? new Date().toISOString().slice(0, 10));
const selectedWindowId = ref<number | null>(null);
const error = ref<string | null>(null);

const { gates, isLoading: gatesLoading } = useGates();
const { windows, isLoading, isError, enabled } = useSlotAvailability(gate, date);
const reschedule = useRescheduleAppointment();

async function submit(): Promise<void> {
    error.value = null;

    if (selectedWindowId.value === null) {
        error.value = 'Pilih window tujuan lebih dulu.';
        return;
    }

    try {
        const updated = await reschedule.mutateAsync({
            id: props.appointment.id,
            slotWindowId: selectedWindowId.value,
            version: props.appointment.version,
        });
        emit('rescheduled', updated);
    } catch (e) {
        error.value = extractError(e);
    }
}

function extractError(e: unknown): string {
    if (isAxiosError(e)) {
        const data = e.response?.data as { error?: string; message?: string } | undefined;
        if (data?.error === 'version_conflict') return 'Booking sudah berubah. Muat ulang lalu coba lagi.';
        if (data?.error === 'slot_unavailable') return 'Window tujuan penuh atau ditutup.';
        return data?.message ?? 'Gagal reschedule. Coba lagi.';
    }
    return 'Terjadi kesalahan. Coba lagi.';
}
</script>

<template>
    <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-10" role="dialog" aria-modal="true">
        <div class="w-full max-w-lg bg-white rounded-xl shadow-lg p-6 space-y-4">
            <header class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Pindah jadwal — {{ appointment.booking_code }}</h2>
                <button type="button" class="text-gray-400 hover:text-gray-700" @click="emit('cancel')">✕</button>
            </header>

            <p v-if="error" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-2">{{ error }}</p>

            <div class="flex flex-wrap items-end gap-3">
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Gate</span>
                    <select
                        v-model.number="gate"
                        :disabled="gatesLoading"
                        class="w-44 rounded-md border border-gray-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
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

            <p v-if="!enabled" class="text-sm text-gray-500">Pilih gate untuk melihat window.</p>
            <p v-else-if="isLoading" class="text-sm text-gray-500">Memuat window…</p>
            <p v-else-if="isError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-2">
                Gagal memuat window. Coba lagi.
            </p>
            <p v-else-if="windows.length === 0" class="text-sm text-gray-500">Tidak ada window terbuka.</p>

            <ul v-else class="max-h-64 overflow-y-auto space-y-2" data-testid="window-list">
                <li v-for="w in windows" :key="w.id">
                    <button
                        type="button"
                        :disabled="w.remaining <= 0"
                        class="w-full flex items-center justify-between rounded-md border px-3 py-2 text-sm disabled:opacity-40"
                        :class="selectedWindowId === w.id ? 'border-indigo-600 bg-indigo-50' : 'border-gray-300 hover:bg-gray-50'"
                        data-testid="window-option"
                        @click="selectedWindowId = w.id"
                    >
                        <span>{{ w.start_time.slice(0, 5) }}–{{ w.end_time.slice(0, 5) }}</span>
                        <span class="text-gray-500">sisa {{ w.remaining }}/{{ w.capacity }}</span>
                    </button>
                </li>
            </ul>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-md px-4 py-2 text-sm text-gray-600 hover:text-gray-900" @click="emit('cancel')">
                    Batal
                </button>
                <button
                    type="button"
                    :disabled="reschedule.isPending.value"
                    class="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                    data-testid="reschedule-submit"
                    @click="submit"
                >
                    {{ reschedule.isPending.value ? 'Memproses…' : 'Pindahkan' }}
                </button>
            </div>
        </div>
    </div>
</template>
