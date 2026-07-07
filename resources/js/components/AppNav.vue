<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

/**
 * Navbar bersama semua halaman ber-auth. Satu-satunya sumber daftar link:
 * gating per link = permission (bukan nama role) supaya konsisten dengan
 * otorisasi server — link /reports juga butuh company_id, cermin aturan 403.
 */
const auth = useAuthStore();
const router = useRouter();

async function onLogout(): Promise<void> {
    await auth.logout();
    await router.push({ name: 'login' });
}

const linkClass = 'px-3 py-2 text-sm text-gray-600 hover:text-gray-900 rounded-md hover:bg-gray-100';
const activeClass = 'text-indigo-700 font-medium bg-indigo-50';
</script>

<template>
    <header class="bg-white border-b px-4 py-2 flex items-center gap-2 flex-wrap">
        <RouterLink to="/" class="font-semibold text-gray-900 px-2" data-testid="brand">TAS</RouterLink>

        <nav class="flex items-center gap-1 flex-wrap" data-testid="nav-links">
            <RouterLink v-if="auth.can('slot.read')" to="/slots" :class="linkClass" :exact-active-class="activeClass">
                Slot
            </RouterLink>
            <RouterLink v-if="auth.can('appointment.write')" to="/bookings" :class="linkClass" :exact-active-class="activeClass">
                Booking Saya
            </RouterLink>
            <RouterLink
                v-if="auth.can('report.read') && auth.user?.company_id != null"
                to="/reports"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Laporan
            </RouterLink>
            <RouterLink v-if="auth.can('appointment.read.self')" to="/today" :class="linkClass" :exact-active-class="activeClass">
                Jadwal Hari Ini
            </RouterLink>
            <RouterLink v-if="auth.can('gate.process')" to="/gate" :class="linkClass" :exact-active-class="activeClass">
                Gate
            </RouterLink>
            <RouterLink v-if="auth.can('slot.manage')" to="/planner" :class="linkClass" :exact-active-class="activeClass">
                Kelola Slot
            </RouterLink>
            <RouterLink v-if="auth.can('terminal.manage')" to="/admin" :class="linkClass" :exact-active-class="activeClass">
                Master Data
            </RouterLink>
        </nav>

        <div class="ml-auto flex items-center gap-3">
            <span class="text-sm text-gray-600" data-testid="user-name">{{ auth.user?.name }}</span>
            <button
                type="button"
                class="text-sm text-gray-600 hover:text-gray-900"
                data-testid="logout"
                @click="onLogout"
            >
                Keluar
            </button>
        </div>
    </header>
</template>
