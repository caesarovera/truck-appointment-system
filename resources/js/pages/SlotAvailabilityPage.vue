<script setup lang="ts">
import { computed, ref } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { useGates } from '@/composables/useGates';
import { useSlotAvailability } from '@/composables/useSlotAvailability';
import BookingForm from '@/components/BookingForm.vue';
import type { BookedAppointment, SlotWindow } from '@/types/api';

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

const auth = useAuthStore();
const gate = ref<number | null>(null);
const date = ref<string>(today());

const { gates, isLoading: gatesLoading } = useGates();
const { windows, isLoading, isFetching, isError, enabled } = useSlotAvailability(gate, date);

const canBook = computed(() => auth.can('appointment.write'));

// Window yang sedang dibooking (membuka modal) + booking sukses terakhir.
const selected = ref<SlotWindow | null>(null);
const lastBooking = ref<BookedAppointment | null>(null);

function onBooked(appointment: BookedAppointment): void {
    lastBooking.value = appointment;
    selected.value = null;
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
            <h1 class="font-semibold text-gray-900">Ketersediaan Slot</h1>
        </header>

        <main class="p-6 space-y-6">
            <form class="flex flex-wrap items-end gap-4" @submit.prevent>
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Gate</span>
                    <select
                        v-model.number="gate"
                        :disabled="gatesLoading"
                        class="w-48 rounded-md border border-gray-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option :value="null" disabled>{{ gatesLoading ? 'Memuat gate…' : 'Pilih gate' }}</option>
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
                <span v-if="isFetching" class="text-sm text-gray-500 pb-2">Memuat…</span>
            </form>

            <p v-if="lastBooking" role="status" class="text-sm text-green-700 bg-green-50 rounded-md p-3">
                Booking berhasil — kode <strong>{{ lastBooking.booking_code }}</strong> ({{ lastBooking.status }}).
            </p>

            <p v-if="!enabled" class="text-sm text-gray-500">Pilih gate untuk melihat slot.</p>

            <p v-else-if="isLoading" class="text-sm text-gray-500">Memuat ketersediaan…</p>

            <p v-else-if="isError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">
                Gagal memuat ketersediaan slot. Coba lagi.
            </p>

            <p v-else-if="windows.length === 0" class="text-sm text-gray-500">
                Tidak ada slot terbuka untuk gate &amp; tanggal ini.
            </p>

            <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="slot-list">
                <li
                    v-for="w in windows"
                    :key="w.id"
                    class="bg-white rounded-lg border p-4 space-y-2"
                    data-testid="slot-card"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-900">{{ w.start_time.slice(0, 5) }}–{{ w.end_time.slice(0, 5) }}</span>
                        <span
                            class="text-xs rounded-full px-2 py-0.5"
                            :class="w.remaining > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                        >
                            {{ w.remaining > 0 ? 'Tersedia' : 'Penuh' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600">
                        Sisa <strong>{{ w.remaining }}</strong> dari {{ w.capacity }} slot
                    </p>
                    <button
                        v-if="canBook && w.remaining > 0"
                        type="button"
                        class="w-full rounded-md bg-indigo-600 text-white py-1.5 text-sm font-medium hover:bg-indigo-700"
                        data-testid="book-button"
                        @click="selected = w"
                    >
                        Booking
                    </button>
                </li>
            </ul>

            <BookingForm
                v-if="selected"
                :slot-window="selected"
                @booked="onBooked"
                @cancel="selected = null"
            />
        </main>
    </div>
</template>
