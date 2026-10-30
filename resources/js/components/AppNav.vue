<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppLogo from '@/components/AppLogo.vue';
import { canVisit } from '@/router/access';

/**
 * Navbar bersama semua halaman ber-auth. Link tampil = canVisit() — aturan yang
 * SAMA dengan guard router (router/access.ts, alasan permission + identitas ada
 * di sana), jadi link yang tampil pasti bisa dibuka & sebaliknya.
 */
const auth = useAuthStore();
const router = useRouter();

async function onLogout(): Promise<void> {
    await auth.logout();
    await router.push({ name: 'login' });
}

const linkClass = 'px-3 py-2 text-sm text-harbor-100/80 hover:text-white rounded-md hover:bg-white/10 transition-colors';
const activeClass = '!text-white font-medium bg-white/10 ring-1 ring-inset ring-signal-500/60';
</script>

<template>
    <header class="bg-harbor-900 text-white border-b border-harbor-800 px-4 py-2 flex items-center gap-2 flex-wrap shadow-sm">
        <RouterLink to="/" class="flex items-center gap-2 px-2 py-1" data-testid="brand">
            <AppLogo variant="nav" />
            <span class="font-semibold tracking-wide">TAS</span>
        </RouterLink>

        <nav class="flex items-center gap-1 flex-wrap" data-testid="nav-links">
            <RouterLink v-if="canVisit('slots', auth)" to="/slots" :class="linkClass" :exact-active-class="activeClass">
                Slot
            </RouterLink>
            <RouterLink
                v-if="canVisit('bookings', auth)"
                to="/bookings"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Booking Saya
            </RouterLink>
            <RouterLink
                v-if="canVisit('trucks', auth)"
                to="/trucks"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Armada
            </RouterLink>
            <RouterLink
                v-if="canVisit('reports', auth)"
                to="/reports"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Laporan
            </RouterLink>
            <RouterLink
                v-if="canVisit('today', auth)"
                to="/today"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Jadwal Hari Ini
            </RouterLink>
            <RouterLink
                v-if="canVisit('gate', auth)"
                to="/gate"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Gate
            </RouterLink>
            <RouterLink v-if="canVisit('planner', auth)" to="/planner" :class="linkClass" :exact-active-class="activeClass">
                Kelola Slot
            </RouterLink>
            <RouterLink
                v-if="canVisit('gate-history', auth)"
                to="/planner/gate-history"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Riwayat Gate
            </RouterLink>
            <RouterLink v-if="canVisit('admin', auth)" to="/admin" :class="linkClass" :exact-active-class="activeClass">
                Master Data
            </RouterLink>
        </nav>

        <div class="ml-auto flex items-center gap-3">
            <span class="text-sm text-harbor-100/80" data-testid="user-name">{{ auth.user?.name }}</span>
            <button
                type="button"
                class="text-sm text-harbor-100/80 hover:text-white rounded-md px-2 py-1 hover:bg-white/10 transition-colors"
                data-testid="logout"
                @click="onLogout"
            >
                Keluar
            </button>
        </div>
    </header>
</template>
