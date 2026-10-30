<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { canVisit } from '@/router/access';

// Header (brand + logout) kini milik AppNav di layout bersama. Kartu tampil =
// canVisit() — aturan yang sama dengan AppNav & guard router (router/access.ts).
const auth = useAuthStore();
</script>

<template>
    <main class="p-6 space-y-6">
            <div class="rounded-xl bg-harbor-900 text-white px-6 py-5 shadow-sm">
                <h1 class="text-lg">Selamat datang, <strong>{{ auth.user?.name }}</strong></h1>
                <p class="text-sm text-harbor-100/80 mt-1">
                    Role: {{ auth.user?.roles.join(', ') || '—' }}
                    · {{ auth.user?.permissions.length ?? 0 }} izin aktif
                </p>
            </div>

            <nav class="flex flex-wrap gap-3">
                <RouterLink
                    v-if="canVisit('slots', auth)"
                    to="/slots"
                    class="rounded-md bg-signal-600 text-white px-4 py-2 text-sm font-medium hover:bg-signal-700"
                >
                    Ketersediaan Slot
                </RouterLink>
                <RouterLink
                    v-if="canVisit('bookings', auth)"
                    to="/bookings"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Booking Saya
                </RouterLink>
                <RouterLink
                    v-if="canVisit('trucks', auth)"
                    to="/trucks"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Armada Truk
                </RouterLink>
                <RouterLink
                    v-if="canVisit('reports', auth)"
                    to="/reports"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Laporan Perusahaan
                </RouterLink>
                <RouterLink
                    v-if="canVisit('today', auth)"
                    to="/today"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Jadwal Hari Ini
                </RouterLink>
                <RouterLink
                    v-if="canVisit('gate', auth)"
                    to="/gate"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Dashboard Gate
                </RouterLink>
                <RouterLink
                    v-if="canVisit('planner', auth)"
                    to="/planner"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Kelola Slot
                </RouterLink>
                <RouterLink
                    v-if="canVisit('gate-history', auth)"
                    to="/planner/gate-history"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Riwayat Gate
                </RouterLink>
                <RouterLink
                    v-if="canVisit('admin', auth)"
                    to="/admin"
                    class="rounded-md bg-white border border-slate-300 text-slate-700 px-4 py-2 text-sm font-medium hover:bg-slate-50"
                >
                    Master Data
                </RouterLink>
            </nav>
    </main>
</template>
