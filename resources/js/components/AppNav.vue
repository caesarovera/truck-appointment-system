<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppLogo from '@/components/AppLogo.vue';

/**
 * Navbar bersama semua halaman ber-auth. Satu-satunya sumber daftar link:
 * gating per link = permission (bukan nama role) supaya konsisten dengan
 * otorisasi server. Admin punya SEMUA permission (RolePermissionSeeder) tapi
 * TIDAK punya company_id/terminal_id — endpoint /reports, /bookings, /trucks,
 * /gate menolaknya 403 (lihat controller masing-masing: abort_if company_id/
 * terminal_id null). Link-link itu jadi butuh guard identitas tambahan, bukan
 * cuma permission, supaya tak menampilkan link yang pasti gagal saat diklik.
 *
 * /today (Jadwal Hari Ini) beda: backend TAK 403 admin (todayForDriver(admin.id,…)
 * cuma balikin list kosong — admin tak pernah jadi driver_id appointment mana pun),
 * jadi tak ada identitas company/terminal yang bisa dicek. Satu-satunya link yang
 * memang butuh hasRole('driver') di sini — sengaja, bukan pelanggaran aturan di atas.
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
            <RouterLink v-if="auth.can('slot.read')" to="/slots" :class="linkClass" :exact-active-class="activeClass">
                Slot
            </RouterLink>
            <RouterLink
                v-if="auth.can('appointment.write') && auth.user?.company_id != null"
                to="/bookings"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Booking Saya
            </RouterLink>
            <RouterLink
                v-if="auth.can('fleet.manage') && auth.user?.company_id != null"
                to="/trucks"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Armada
            </RouterLink>
            <RouterLink
                v-if="auth.can('report.read') && auth.user?.company_id != null"
                to="/reports"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Laporan
            </RouterLink>
            <RouterLink
                v-if="auth.can('appointment.read.self') && auth.hasRole('driver')"
                to="/today"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Jadwal Hari Ini
            </RouterLink>
            <RouterLink
                v-if="auth.can('gate.process') && auth.user?.terminal_id != null"
                to="/gate"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Gate
            </RouterLink>
            <RouterLink v-if="auth.can('slot.manage')" to="/planner" :class="linkClass" :exact-active-class="activeClass">
                Kelola Slot
            </RouterLink>
            <RouterLink
                v-if="auth.can('slot.manage')"
                to="/planner/gate-history"
                :class="linkClass"
                :exact-active-class="activeClass"
            >
                Riwayat Gate
            </RouterLink>
            <RouterLink v-if="auth.can('terminal.manage')" to="/admin" :class="linkClass" :exact-active-class="activeClass">
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
