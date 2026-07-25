<script setup lang="ts">
/**
 * Placeholder baris saat data list belum datang — pengganti teks "Memuat…".
 *
 * Kenapa bukan teks: tinggi placeholder mendekati tinggi konten aslinya, jadi
 * halaman tidak melompat saat data masuk (layout shift). Bentuknya sengaja
 * meniru kartu list yang dipakai hampir semua halaman (`bg-white rounded-lg border p-4`).
 *
 * Aksesibilitas: animasi tidak mengabarkan apa pun ke pembaca layar, jadi label
 * tetap dirender sebagai `sr-only` di dalam `role="status"`. Balok visualnya
 * `aria-hidden` supaya tak dibacakan sebagai konten kosong.
 */
withDefaults(defineProps<{ rows?: number; label?: string }>(), {
    rows: 3,
    label: 'Memuat…',
});
</script>

<template>
    <div role="status" aria-busy="true" class="space-y-2" data-testid="skeleton">
        <span class="sr-only">{{ label }}</span>
        <div
            v-for="n in rows"
            :key="n"
            aria-hidden="true"
            class="bg-white rounded-lg border p-4 animate-pulse"
        >
            <div class="h-4 w-1/3 rounded bg-gray-200"></div>
            <div class="mt-3 h-3 w-2/3 rounded bg-gray-100"></div>
        </div>
    </div>
</template>
