<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

// Header (brand + logout) kini milik AppNav di layout bersama.
const auth = useAuthStore();
</script>

<template>
    <main class="p-6 space-y-6">
            <div class="rounded-xl bg-harbor-900 text-white px-6 py-5 shadow-sm">
                <p class="text-lg">Selamat datang, <strong>{{ auth.user?.name }}</strong></p>
                <p class="text-sm text-harbor-100/80 mt-1">
                    Role: {{ auth.user?.roles.join(', ') || '—' }}
                    · {{ auth.user?.permissions.length ?? 0 }} izin aktif
                </p>
            </div>

            <nav class="flex flex-wrap gap-3">
                <RouterLink
                    v-if="auth.can('slot.read')"
                    to="/slots"
                    class="rounded-md bg-signal-600 text-white px-4 py-2 text-sm font-medium hover:bg-signal-700"
                >
                    Ketersediaan Slot
                </RouterLink>
                <!--
                    appointment.write/fleet.manage/gate.process/appointment.read.self:
                    admin punya SEMUA permission tapi TIDAK punya company_id/terminal_id/
                    role driver — endpoint di baliknya 403 (atau list kosong utk /today)
                    tanpa identitas itu. Guard tambahan di sini mencerminkan aturan yang
                    sama persis dengan AppNav.vue (2026-08-13) — jangan tampilkan kartu
                    yang pasti gagal/kosong saat diklik admin.
                -->
                <RouterLink
                    v-if="auth.can('appointment.write') && auth.user?.company_id != null"
                    to="/bookings"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Booking Saya
                </RouterLink>
                <RouterLink
                    v-if="auth.can('fleet.manage') && auth.user?.company_id != null"
                    to="/trucks"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Armada Truk
                </RouterLink>
                <!-- report.read + punya company: planner/admin (tanpa company) pakai /planner. -->
                <RouterLink
                    v-if="auth.can('report.read') && auth.user?.company_id != null"
                    to="/reports"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Laporan Perusahaan
                </RouterLink>
                <RouterLink
                    v-if="auth.can('appointment.read.self') && auth.hasRole('driver')"
                    to="/today"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Jadwal Hari Ini
                </RouterLink>
                <RouterLink
                    v-if="auth.can('gate.process') && auth.user?.terminal_id != null"
                    to="/gate"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Dashboard Gate
                </RouterLink>
                <RouterLink
                    v-if="auth.can('slot.manage')"
                    to="/planner"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Kelola Slot
                </RouterLink>
                <RouterLink
                    v-if="auth.can('slot.manage')"
                    to="/planner/gate-history"
                    class="rounded-md bg-white border border-harbor-600 text-harbor-700 px-4 py-2 text-sm font-medium hover:bg-harbor-50"
                >
                    Riwayat Gate
                </RouterLink>
                <RouterLink
                    v-if="auth.can('terminal.manage')"
                    to="/admin"
                    class="rounded-md bg-white border border-slate-300 text-slate-700 px-4 py-2 text-sm font-medium hover:bg-slate-50"
                >
                    Master Data
                </RouterLink>
            </nav>
    </main>
</template>
