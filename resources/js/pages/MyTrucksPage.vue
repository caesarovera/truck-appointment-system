<script setup lang="ts">
import { computed, ref } from 'vue';
import { isAxiosError } from 'axios';

import { useMyTrucks } from '@/composables/useTrucks';
import type { Truck, TruckStatus } from '@/types/api';

const { trucks, isLoading, isError, create, update, remove } = useMyTrucks();

// Form create/edit dipakai bersama: editingId != null → mode edit.
const editingId = ref<number | null>(null);
const plateNo = ref('');
const status = ref<TruckStatus>('ACTIVE');
const formError = ref<string | null>(null);
const confirmingId = ref<number | null>(null);

const isEditing = computed(() => editingId.value !== null);
const busy = computed(
    () => create.isPending.value || update.isPending.value || remove.isPending.value,
);

function resetForm(): void {
    editingId.value = null;
    plateNo.value = '';
    status.value = 'ACTIVE';
    formError.value = null;
}

function startEdit(truck: Truck): void {
    editingId.value = truck.id;
    plateNo.value = truck.plate_no;
    status.value = truck.status === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';
    formError.value = null;
    confirmingId.value = null;
}

async function onSubmit(): Promise<void> {
    formError.value = null;
    const payload = { plate_no: plateNo.value.trim(), status: status.value };

    try {
        if (editingId.value !== null) {
            await update.mutateAsync({ id: editingId.value, ...payload });
        } else {
            await create.mutateAsync(payload);
        }
        resetForm();
    } catch (e) {
        formError.value = mapError(e);
    }
}

async function onDelete(id: number): Promise<void> {
    formError.value = null;
    try {
        await remove.mutateAsync(id);
    } catch (e) {
        formError.value = mapError(e);
    } finally {
        confirmingId.value = null;
    }
}

function mapError(e: unknown): string {
    if (isAxiosError(e)) {
        const data = e.response?.data as
            | { error?: string; message?: string; errors?: Record<string, string[]> }
            | undefined;

        if (data?.error === 'entity_in_use') {
            return 'Truk masih dipakai pada appointment — nonaktifkan (INACTIVE) saja, jangan hapus.';
        }
        if (e.response?.status === 422) {
            const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;
            return first ?? 'Data tidak valid. Periksa plat nomor.';
        }
        return data?.message ?? 'Gagal menyimpan. Coba lagi.';
    }
    return 'Terjadi kesalahan. Coba lagi.';
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b px-6 py-4">
            <h1 class="font-semibold text-gray-900">Armada Truk</h1>
        </header>

        <main class="p-6 space-y-6">
            <p v-if="formError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">
                {{ formError }}
            </p>

            <!-- Form create/edit -->
            <form
                class="flex flex-wrap items-end gap-3 bg-white border rounded-lg p-4"
                data-testid="truck-form"
                @submit.prevent="onSubmit"
            >
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Plat nomor</span>
                    <input
                        v-model="plateNo"
                        type="text"
                        required
                        maxlength="20"
                        placeholder="mis. B 1234 XY"
                        data-testid="plate-input"
                        class="w-48 rounded-md border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </label>
                <label class="block space-y-1">
                    <span class="text-sm font-medium text-gray-700">Status</span>
                    <select
                        v-model="status"
                        data-testid="status-select"
                        class="w-40 rounded-md border border-gray-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="ACTIVE">ACTIVE</option>
                        <option value="INACTIVE">INACTIVE</option>
                    </select>
                </label>
                <button
                    type="submit"
                    :disabled="busy"
                    data-testid="submit-truck"
                    class="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ isEditing ? 'Simpan' : 'Tambah' }}
                </button>
                <button
                    v-if="isEditing"
                    type="button"
                    data-testid="cancel-edit"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    @click="resetForm"
                >
                    Batal
                </button>
            </form>

            <!-- List -->
            <p v-if="isLoading" class="text-sm text-gray-500">Memuat armada…</p>

            <p v-else-if="isError" role="alert" class="text-sm text-red-600 bg-red-50 rounded-md p-3">
                Gagal memuat armada. Coba lagi.
            </p>

            <p v-else-if="trucks.length === 0" class="text-sm text-gray-500">
                Belum ada truk. Tambahkan lewat form di atas.
            </p>

            <ul v-else class="space-y-2" data-testid="truck-list">
                <li
                    v-for="t in trucks"
                    :key="t.id"
                    class="bg-white rounded-lg border p-4 flex items-center justify-between gap-4"
                    data-testid="truck-row"
                >
                    <div class="flex items-center gap-3">
                        <span class="font-medium text-gray-900">{{ t.plate_no }}</span>
                        <span
                            class="text-xs rounded-full px-2 py-0.5"
                            :class="t.status === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'"
                        >
                            {{ t.status }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <template v-if="confirmingId === t.id">
                            <span class="text-sm text-gray-600">Hapus truk ini?</span>
                            <button
                                type="button"
                                :disabled="busy"
                                data-testid="confirm-delete"
                                class="rounded-md bg-red-600 text-white px-3 py-1.5 text-sm hover:bg-red-700 disabled:opacity-50"
                                @click="onDelete(t.id)"
                            >
                                Ya, hapus
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                                @click="confirmingId = null"
                            >
                                Batal
                            </button>
                        </template>
                        <template v-else>
                            <button
                                type="button"
                                data-testid="edit-truck"
                                class="rounded-md border border-indigo-600 text-indigo-700 px-3 py-1.5 text-sm hover:bg-indigo-50"
                                @click="startEdit(t)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                data-testid="delete-truck"
                                class="rounded-md border border-red-300 text-red-700 px-3 py-1.5 text-sm hover:bg-red-50"
                                @click="confirmingId = t.id"
                            >
                                Hapus
                            </button>
                        </template>
                    </div>
                </li>
            </ul>
        </main>
    </div>
</template>
