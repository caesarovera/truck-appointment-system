<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { isAxiosError } from 'axios';
import { useMyAppointments } from '@/composables/useMyAppointments';
import { useCancelAppointment } from '@/composables/useCancelAppointment';
import RescheduleDialog from '@/components/RescheduleDialog.vue';
import type { Appointment } from '@/types/api';

// Pra-kedatangan → boleh batal & pindah jadwal (cocok dgn AppointmentStatus).
const MANAGEABLE = ['BOOKED', 'CONFIRMED'];
const STATUSES = ['BOOKED', 'CONFIRMED', 'ARRIVED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO_SHOW'];

const status = ref('');
const { appointments, isLoading, isError } = useMyAppointments(status);
const cancelMutation = useCancelAppointment();

const confirmingId = ref<number | null>(null);
const rescheduling = ref<Appointment | null>(null);
const error = ref<string | null>(null);

function canManage(a: Appointment): boolean {
    return MANAGEABLE.includes(a.status);
}

function onRescheduled(): void {
    // Daftar & ketersediaan ter-invalidate oleh mutation → cukup tutup dialog.
    rescheduling.value = null;
}

async function confirmCancel(a: Appointment): Promise<void> {
    error.value = null;
    try {
        await cancelMutation.mutateAsync({ id: a.id, version: a.version });
        confirmingId.value = null;
    } catch (e) {
        error.value = extractError(e);
    }
}

function extractError(e: unknown): string {
    if (isAxiosError(e)) {
        const data = e.response?.data as { error?: string; message?: string } | undefined;
        if (data?.error === 'version_conflict') return 'Booking sudah berubah. Muat ulang lalu coba lagi.';
        if (data?.error === 'invalid_state') return 'Booking tidak bisa dibatalkan pada status ini.';
        return data?.message ?? 'Gagal membatalkan. Coba lagi.';
    }
    return 'Terjadi kesalahan. Coba lagi.';
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
            <h1 class="font-semibold text-gray-900">Booking Saya</h1>
            <div class="flex items-center gap-4 text-sm">
                <RouterLink to="/slots" class="text-indigo-600 hover:text-indigo-800">+ Booking baru</RouterLink>
                <RouterLink to="/" class="text-gray-600 hover:text-gray-900">← Dashboard</RouterLink>
            </div>
        </header>

        <main class="p-6 space-y-6">
            <label class="inline-flex flex-col space-y-1">
                <span class="text-sm font-medium text-gray-700">Status</span>
                <select
                    v-model="status"
                    class="w-48 rounded-md border border-gray-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Semua</option>
                    <option v-for="s in STATUSES" :key="s" :value="s">{{ s }}</option>
                </select>
            </label>

            <p v-if="error" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">{{ error }}</p>

            <p v-if="isLoading" class="text-sm text-gray-500">Memuat booking…</p>

            <p v-else-if="isError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">
                Gagal memuat daftar booking. Coba lagi.
            </p>

            <p v-else-if="appointments.length === 0" class="text-sm text-gray-500">Belum ada booking.</p>

            <ul v-else class="space-y-3" data-testid="booking-list">
                <li
                    v-for="a in appointments"
                    :key="a.id"
                    class="bg-white rounded-lg border p-4 space-y-2"
                    data-testid="booking-row"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-900">{{ a.booking_code }}</span>
                        <span class="text-xs rounded-full px-2 py-0.5 bg-gray-100 text-gray-700">{{ a.status }}</span>
                    </div>

                    <p v-if="a.slot_window" class="text-sm text-gray-600">
                        {{ a.slot_window.date }} ·
                        {{ a.slot_window.start_time.slice(0, 5) }}–{{ a.slot_window.end_time.slice(0, 5) }}
                    </p>
                    <p class="text-sm text-gray-600">
                        {{ a.move_type }}
                        <template v-if="a.truck"> · {{ a.truck.plate_no }}</template>
                        <template v-if="a.driver"> · {{ a.driver.name }}</template>
                        <template v-if="a.containers[0]"> · {{ a.containers[0].container_no }}</template>
                    </p>

                    <div v-if="canManage(a)" class="flex items-center gap-2 pt-1">
                        <template v-if="confirmingId === a.id">
                            <span class="text-sm text-gray-700 mr-1">Batalkan booking ini?</span>
                            <button
                                type="button"
                                :disabled="cancelMutation.isPending.value"
                                class="rounded-md bg-red-600 text-white px-3 py-1 text-sm hover:bg-red-700 disabled:opacity-50"
                                data-testid="confirm-cancel"
                                @click="confirmCancel(a)"
                            >
                                {{ cancelMutation.isPending.value ? 'Memproses…' : 'Ya, batalkan' }}
                            </button>
                            <button type="button" class="text-sm text-gray-600 hover:text-gray-900" @click="confirmingId = null">
                                Tidak
                            </button>
                        </template>
                        <template v-else>
                            <button
                                type="button"
                                class="rounded-md border border-indigo-300 text-indigo-700 px-3 py-1 text-sm hover:bg-indigo-50"
                                data-testid="reschedule-button"
                                @click="rescheduling = a"
                            >
                                Pindah jadwal
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-red-300 text-red-700 px-3 py-1 text-sm hover:bg-red-50"
                                data-testid="cancel-button"
                                @click="confirmingId = a.id"
                            >
                                Batalkan
                            </button>
                        </template>
                    </div>
                </li>
            </ul>

            <RescheduleDialog
                v-if="rescheduling"
                :appointment="rescheduling"
                @rescheduled="onRescheduled"
                @cancel="rescheduling = null"
            />
        </main>
    </div>
</template>
