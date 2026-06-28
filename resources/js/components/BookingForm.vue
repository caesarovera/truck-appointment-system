<script setup lang="ts">
import { ref } from 'vue';
import { isAxiosError } from 'axios';
import { useFleet } from '@/composables/useFleet';
import { useBookAppointment } from '@/composables/useBookAppointment';
import type { BookedAppointment, MoveType, SlotWindow } from '@/types/api';

const props = defineProps<{ slotWindow: SlotWindow }>();
const emit = defineEmits<{ booked: [BookedAppointment]; cancel: [] }>();

const { trucks, drivers, isLoading: fleetLoading } = useFleet();
const booking = useBookAppointment();

const truckId = ref<number | null>(null);
const driverId = ref<number | null>(null);
const moveType = ref<MoveType>('DELIVERY');
const containerNo = ref('');
const isoType = ref('');
const size = ref<number | null>(null);
const error = ref<string | null>(null);

function idempotencyKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `idem-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

async function submit(): Promise<void> {
    error.value = null;

    if (truckId.value === null || driverId.value === null) {
        error.value = 'Pilih truk & sopir lebih dulu.';
        return;
    }

    try {
        const appointment = await booking.mutateAsync({
            payload: {
                slot_window_id: props.slotWindow.id,
                truck_id: truckId.value,
                driver_id: driverId.value,
                move_type: moveType.value,
                container_no: containerNo.value,
                iso_type: isoType.value !== '' ? isoType.value : undefined,
                size: size.value ?? undefined,
            },
            idempotencyKey: idempotencyKey(),
        });
        emit('booked', appointment);
    } catch (e) {
        error.value = extractError(e);
    }
}

function extractError(e: unknown): string {
    if (isAxiosError(e)) {
        const data = e.response?.data as { error?: string; message?: string } | undefined;
        if (data?.error === 'slot_unavailable') return 'Slot sudah penuh atau ditutup.';
        if (data?.error === 'duplicate_booking') return 'Kontainer ini sudah dibooking di window tersebut.';
        return data?.message ?? 'Booking gagal. Periksa data lalu coba lagi.';
    }
    return 'Terjadi kesalahan. Coba lagi.';
}
</script>

<template>
    <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-10" role="dialog" aria-modal="true">
        <form class="w-full max-w-md bg-white rounded-xl shadow-lg p-6 space-y-4" @submit.prevent="submit">
            <header class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">
                    Booking slot {{ slotWindow.start_time.slice(0, 5) }}–{{ slotWindow.end_time.slice(0, 5) }}
                </h2>
                <button type="button" class="text-gray-400 hover:text-gray-700" @click="emit('cancel')">✕</button>
            </header>

            <p v-if="error" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-2">{{ error }}</p>

            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700">Truk</span>
                <select
                    v-model.number="truckId"
                    :disabled="fleetLoading"
                    required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option :value="null" disabled>Pilih truk</option>
                    <option v-for="t in trucks" :key="t.id" :value="t.id">{{ t.plate_no }}</option>
                </select>
            </label>

            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700">Sopir</span>
                <select
                    v-model.number="driverId"
                    :disabled="fleetLoading"
                    required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option :value="null" disabled>Pilih sopir</option>
                    <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Jenis</span>
                    <select
                        v-model="moveType"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="DELIVERY">DELIVERY</option>
                        <option value="RECEIVAL">RECEIVAL</option>
                    </select>
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Ukuran</span>
                    <select
                        v-model.number="size"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option :value="null">—</option>
                        <option :value="20">20</option>
                        <option :value="40">40</option>
                    </select>
                </label>
            </div>

            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700">No. kontainer</span>
                <input
                    v-model="containerNo"
                    type="text"
                    required
                    maxlength="20"
                    placeholder="mis. MAUU1234567"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </label>

            <label class="block space-y-1">
                <span class="text-sm font-medium text-gray-700">Tipe ISO <span class="text-gray-400">(opsional)</span></span>
                <input
                    v-model="isoType"
                    type="text"
                    maxlength="10"
                    placeholder="mis. 22G1"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-md px-4 py-2 text-sm text-gray-600 hover:text-gray-900" @click="emit('cancel')">
                    Batal
                </button>
                <button
                    type="submit"
                    :disabled="booking.isPending.value"
                    class="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ booking.isPending.value ? 'Memproses…' : 'Booking' }}
                </button>
            </div>
        </form>
    </div>
</template>
