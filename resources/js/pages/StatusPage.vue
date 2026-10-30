<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

// Dipakai 2 rute di router/index.ts: `forbidden` (guard canVisit menolak) &
// `not-found` (catch-all). Murni props → tak perlu useRoute.
const props = defineProps<{ code: 403 | 404; from?: string }>();

const title = computed(() => (props.code === 403 ? 'Tidak punya akses' : 'Halaman tidak ditemukan'));
</script>

<template>
    <main class="p-6">
        <div class="max-w-lg mx-auto mt-10 rounded-xl bg-white border border-slate-200 px-6 py-8 text-center shadow-sm">
            <p class="text-4xl font-semibold text-harbor-700">{{ code }}</p>
            <h1 class="text-lg font-semibold text-slate-800 mt-2">{{ title }}</h1>
            <p v-if="code === 403" class="text-sm text-slate-600 mt-2">
                Akun Anda tidak memiliki izin untuk membuka halaman
                <code v-if="from" class="font-mono text-slate-800">{{ from }}</code><template v-else>ini</template>.
                Hubungi admin bila Anda seharusnya punya akses.
            </p>
            <p v-else class="text-sm text-slate-600 mt-2">Alamat yang Anda buka tidak ada atau sudah dipindah.</p>
            <RouterLink
                to="/"
                class="inline-block mt-6 rounded-md bg-signal-600 text-white px-4 py-2 text-sm font-medium hover:bg-signal-700"
            >
                Ke Beranda
            </RouterLink>
        </div>
    </main>
</template>
