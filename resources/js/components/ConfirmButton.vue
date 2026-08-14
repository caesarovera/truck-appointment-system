<script setup lang="ts">
import { computed } from 'vue';
import Spinner from '@/components/Spinner.vue';

/**
 * Tombol aksi 2-langkah: klik pertama membuka konfirmasi inline ("Yakin?" + Ya/Batal),
 * klik "Ya" baru memicu aksi sesungguhnya. Dipakai untuk semua aksi yang mengubah
 * status/data (hapus, tutup window, gate-in/out, batalkan) — supaya tak ada aksi
 * ireversibel yang ke-trigger dari sentuhan/klik tak sengaja.
 *
 * Terbuka-tertutupnya panel konfirmasi dikontrol parent lewat `open` (v-model),
 * bukan state internal — supaya parent bisa menutupnya sendiri setelah mutasi
 * sukses/gagal (pola yang sudah dipakai manual di MyTrucksPage/MyBookingsPage).
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        pending?: boolean;
        disabled?: boolean;
        label: string;
        confirmLabel: string;
        confirmText?: string;
        cancelText?: string;
        variant?: 'danger' | 'success' | 'primary';
        outline?: boolean;
        size?: 'sm' | 'md';
        testid?: string;
    }>(),
    {
        pending: false,
        disabled: false,
        confirmText: 'Ya',
        cancelText: 'Batal',
        variant: 'primary',
        outline: true,
        size: 'sm',
        testid: undefined,
    },
);

const emit = defineEmits<{ 'update:open': [boolean]; confirm: [] }>();

const sizeClasses = computed(() =>
    props.size === 'md' ? 'px-4 py-2 text-sm' : 'px-3 py-1.5 text-sm',
);

const solidClasses = computed(() => {
    switch (props.variant) {
        case 'danger':
            return 'bg-red-600 text-white hover:bg-red-700';
        case 'success':
            return 'bg-green-600 text-white hover:bg-green-700';
        default:
            return 'bg-signal-600 text-white hover:bg-signal-700';
    }
});

const outlineClasses = computed(() => {
    switch (props.variant) {
        case 'danger':
            return 'border border-red-300 text-red-700 hover:bg-red-50';
        case 'success':
            return 'border border-green-300 text-green-700 hover:bg-green-50';
        default:
            return 'border border-harbor-300 text-harbor-700 hover:bg-harbor-50';
    }
});

const idleClasses = computed(() => (props.outline ? outlineClasses.value : solidClasses.value));
</script>

<template>
    <span v-if="open" class="inline-flex items-center gap-2">
        <span class="text-sm text-gray-700">{{ confirmLabel }}</span>
        <button
            type="button"
            :disabled="pending"
            class="rounded-md font-medium disabled:opacity-50 transition-colors inline-flex items-center gap-1.5"
            :class="[sizeClasses, solidClasses]"
            :data-testid="testid ? `${testid}-confirm` : undefined"
            @click="emit('confirm')"
        >
            <Spinner v-if="pending" />
            {{ pending ? 'Memproses…' : confirmText }}
        </button>
        <button
            type="button"
            :disabled="pending"
            class="rounded-md px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 disabled:opacity-50"
            :data-testid="testid ? `${testid}-cancel` : undefined"
            @click="emit('update:open', false)"
        >
            {{ cancelText }}
        </button>
    </span>
    <button
        v-else
        type="button"
        :disabled="disabled"
        class="rounded-md font-medium disabled:opacity-50 transition-colors"
        :class="[sizeClasses, idleClasses]"
        :data-testid="testid"
        @click="emit('update:open', true)"
    >
        {{ label }}
    </button>
</template>
