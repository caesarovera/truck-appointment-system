import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/LoginPage.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/',
        name: 'dashboard',
        component: () => import('@/pages/DashboardPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/slots',
        name: 'slots',
        component: () => import('@/pages/SlotAvailabilityPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/bookings',
        name: 'bookings',
        component: () => import('@/pages/MyBookingsPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/today',
        name: 'today',
        component: () => import('@/pages/DriverSchedulePage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/gate',
        name: 'gate',
        component: () => import('@/pages/GateDashboardPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/planner',
        name: 'planner',
        component: () => import('@/pages/PlannerWindowsPage.vue'),
        meta: { requiresAuth: true },
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

    return true;
});
