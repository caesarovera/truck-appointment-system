<script setup lang="ts">
/**
 * Ikon jangkar — glyph brand TAS (tema "kepelabuhanan Indonesia", lihat
 * resources/css/app.css). Sumber tunggal: sebelum ini glyph yang sama
 * di-duplikasi manual 3x (AppNav + 2 tempat di LoginPage). `public/favicon.svg`
 * pakai path yang SAMA (di-hardcode hex karena favicon di luar pipeline
 * Tailwind) — kalau glyph di sini berubah, favicon ikut disamakan manual.
 *
 * `variant` mencerminkan 3 pemakaian yang sudah ada, bukan API generik:
 * - `nav`     — AppNav, badge kecil di atas latar harbor-900.
 * - `hero`    — LoginPage panel kiri, badge besar di atas latar harbor-900.
 * - `compact` — LoginPage header mobile, badge medium di atas latar terang.
 */
withDefaults(defineProps<{ variant?: 'nav' | 'hero' | 'compact' }>(), { variant: 'nav' });

const badgeClasses: Record<string, string> = {
    nav: 'h-7 w-7 rounded-md bg-signal-500',
    hero: 'h-11 w-11 rounded-lg bg-signal-500/90 shadow-lg',
    compact: 'h-10 w-10 rounded-lg bg-harbor-900',
};
const iconClasses: Record<string, string> = {
    nav: 'h-4 w-4',
    hero: 'h-6 w-6',
    compact: 'h-5 w-5',
};
</script>

<template>
    <span class="grid place-items-center text-white" :class="badgeClasses[variant]" aria-hidden="true">
        <svg
            viewBox="0 0 24 24"
            :class="iconClasses[variant]"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <circle cx="12" cy="5" r="2.2" />
            <path d="M12 7.2V21" />
            <path d="M5 12h14" />
            <path d="M5 12c0 4 3 8 7 9c4-1 7-5 7-9" />
        </svg>
    </span>
</template>
