import type { AuthUser } from '@/types/api';

/** Bagian auth store yang dibutuhkan aturan akses (store asli & mock test sama-sama cocok). */
export interface AccessSubject {
    can(permission: string): boolean;
    hasRole(role: string): boolean;
    readonly user: Pick<AuthUser, 'company_id' | 'terminal_id'> | null;
}

/**
 * Satu-satunya sumber aturan "boleh buka halaman ini?" — dipakai guard router,
 * AppNav, dan kartu DashboardPage. Gating = permission (bukan nama role) supaya
 * konsisten dengan otorisasi server. Admin punya SEMUA permission
 * (RolePermissionSeeder) tapi TIDAK punya company_id/terminal_id — endpoint
 * /reports, /bookings, /trucks, /gate menolaknya 403 (controller masing-masing:
 * abort_if company_id/terminal_id null). Rute itu jadi butuh guard identitas
 * tambahan, bukan cuma permission.
 *
 * /today (Jadwal Hari Ini) beda: backend TAK 403 admin (todayForDriver(admin.id,…)
 * cuma balikin list kosong — admin tak pernah jadi driver_id appointment mana pun),
 * jadi tak ada identitas company/terminal yang bisa dicek. Satu-satunya rute yang
 * memang butuh hasRole('driver') — sengaja, bukan pelanggaran aturan di atas.
 *
 * Ini UX, bukan batas keamanan: API tetap menolak sendiri.
 */
const RULES = {
    slots: (a: AccessSubject) => a.can('slot.read'),
    bookings: (a: AccessSubject) => a.can('appointment.write') && a.user?.company_id != null,
    trucks: (a: AccessSubject) => a.can('fleet.manage') && a.user?.company_id != null,
    // report.read + punya company: planner/admin (tanpa company) pakai /planner.
    reports: (a: AccessSubject) => a.can('report.read') && a.user?.company_id != null,
    today: (a: AccessSubject) => a.can('appointment.read.self') && a.hasRole('driver'),
    gate: (a: AccessSubject) => a.can('gate.process') && a.user?.terminal_id != null,
    planner: (a: AccessSubject) => a.can('slot.manage'),
    // slot.manage = aktor sama dgn backend hasAnyRole(admin,planner).
    'gate-history': (a: AccessSubject) => a.can('slot.manage'),
    admin: (a: AccessSubject) => a.can('terminal.manage'),
} satisfies Record<string, (a: AccessSubject) => boolean>;

export type GuardedRoute = keyof typeof RULES;

/** Semua nama rute yang dijaga — test memastikan tiap nama benar-benar ada di router. */
export const GUARDED_ROUTES = Object.keys(RULES) as GuardedRoute[];

export function isGuardedRoute(name: unknown): name is GuardedRoute {
    return typeof name === 'string' && Object.hasOwn(RULES, name);
}

export function canVisit(route: GuardedRoute, subject: AccessSubject): boolean {
    return RULES[route](subject);
}
