<script setup lang="ts">
/**
 * Spinner inline kecil untuk teks tombol saat mutasi berjalan ("Memproses…").
 * `aria-hidden` — status loading sudah diumumkan lewat teks tombol itu sendiri.
 */
withDefaults(defineProps<{ size?: 'sm' | 'md' }>(), { size: 'sm' });
</script>

<template>
    <svg
        aria-hidden="true"
        class="inline animate-spin"
        :class="size === 'sm' ? 'h-3.5 w-3.5' : 'h-5 w-5'"
        viewBox="0 0 24 24"
        fill="none"
    >
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
    </svg>
</template>
