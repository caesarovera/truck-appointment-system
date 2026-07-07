<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

// Header (brand + logout) kini milik AppNav di layout bersama.
const auth = useAuthStore();
</script>

<template>
    <main class="p-6 space-y-4">
            <div class="space-y-2">
                <p class="text-gray-900">Halo, <strong>{{ auth.user?.name }}</strong></p>
                <p class="text-sm text-gray-600">Role: {{ auth.user?.roles.join(', ') || '—' }}</p>
                <p class="text-sm text-gray-600">{{ auth.user?.permissions.length ?? 0 }} izin aktif</p>
            </div>

            <nav class="flex flex-wrap gap-3">
                <RouterLink
                    v-if="auth.can('slot.read')"
                    to="/slots"
                    class="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-medium hover:bg-indigo-700"
                >
                    Ketersediaan Slot
                </RouterLink>
                <RouterLink
                    v-if="auth.can('appointment.write')"
                    to="/bookings"
                    class="rounded-md bg-white border border-indigo-600 text-indigo-700 px-4 py-2 text-sm font-medium hover:bg-indigo-50"
                >
                    Booking Saya
                </RouterLink>
                <!-- report.read + punya company: planner/admin (tanpa company) pakai /planner. -->
                <RouterLink
                    v-if="auth.can('report.read') && auth.user?.company_id != null"
                    to="/reports"
                    class="rounded-md bg-white border border-indigo-600 text-indigo-700 px-4 py-2 text-sm font-medium hover:bg-indigo-50"
                >
                    Laporan Perusahaan
                </RouterLink>
                <RouterLink
                    v-if="auth.can('appointment.read.self')"
                    to="/today"
                    class="rounded-md bg-white border border-indigo-600 text-indigo-700 px-4 py-2 text-sm font-medium hover:bg-indigo-50"
                >
                    Jadwal Hari Ini
                </RouterLink>
                <RouterLink
                    v-if="auth.can('gate.process')"
                    to="/gate"
                    class="rounded-md bg-white border border-indigo-600 text-indigo-700 px-4 py-2 text-sm font-medium hover:bg-indigo-50"
                >
                    Dashboard Gate
                </RouterLink>
                <RouterLink
                    v-if="auth.can('slot.manage')"
                    to="/planner"
                    class="rounded-md bg-white border border-indigo-600 text-indigo-700 px-4 py-2 text-sm font-medium hover:bg-indigo-50"
                >
                    Kelola Slot
                </RouterLink>
                <RouterLink
                    v-if="auth.can('terminal.manage')"
                    to="/admin"
                    class="rounded-md bg-white border border-gray-600 text-gray-700 px-4 py-2 text-sm font-medium hover:bg-gray-50"
                >
                    Master Data
                </RouterLink>
            </nav>
    </main>
</template>
