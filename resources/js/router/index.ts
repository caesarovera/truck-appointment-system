import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { canVisit, isGuardedRoute } from '@/router/access';

const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/LoginPage.vue'),
        meta: { guestOnly: true },
    },
    {
        // Layout bersama (navbar) untuk semua halaman ber-auth. requiresAuth
        // cukup di sini: Vue Router menggabungkan meta parent ke tiap child.
        path: '/',
        component: () => import('@/components/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('@/pages/DashboardPage.vue'),
            },
            {
                path: 'slots',
                name: 'slots',
                component: () => import('@/pages/SlotAvailabilityPage.vue'),
            },
            {
                path: 'bookings',
                name: 'bookings',
                component: () => import('@/pages/MyBookingsPage.vue'),
            },
            {
                path: 'trucks',
                name: 'trucks',
                component: () => import('@/pages/MyTrucksPage.vue'),
            },
            {
                path: 'today',
                name: 'today',
                component: () => import('@/pages/DriverSchedulePage.vue'),
            },
            {
                path: 'gate',
                name: 'gate',
                component: () => import('@/pages/GateDashboardPage.vue'),
            },
            {
                path: 'planner',
                name: 'planner',
                component: () => import('@/pages/PlannerWindowsPage.vue'),
            },
            {
                path: 'planner/gate-history',
                name: 'gate-history',
                component: () => import('@/pages/GateHistoryPage.vue'),
            },
            {
                path: 'reports',
                name: 'reports',
                component: () => import('@/pages/MyUtilizationPage.vue'),
            },
            {
                path: 'admin',
                name: 'admin',
                component: () => import('@/pages/AdminPage.vue'),
            },
            {
                path: 'forbidden',
                name: 'forbidden',
                component: () => import('@/pages/StatusPage.vue'),
                props: (route) => ({ code: 403, from: typeof route.query.from === 'string' ? route.query.from : undefined }),
            },
            {
                // Catch-all DI DALAM layout: tamu tetap diarahkan ke login dulu
                // (requiresAuth diwariskan), URL salah tetap terlihat di address bar.
                path: ':pathMatch(.*)*',
                name: 'not-found',
                component: () => import('@/pages/StatusPage.vue'),
                props: { code: 404 },
            },
        ],
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // Pulihkan sesi sekali bila ada token tersimpan tapi user belum dimuat.
    if (auth.token !== null && auth.user === null) {
        await auth.restore();
    }

    if (to.meta.requiresAuth === true && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly === true && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    // Halaman di luar hak role → pesan jelas, bukan skeleton lalu "data kosong"
    // dari query yang 403 (temuan Playwright 2026-10-30).
    if (isGuardedRoute(to.name) && !canVisit(to.name, auth)) {
        return { name: 'forbidden', query: { from: to.fullPath } };
    }

    return true;
});
