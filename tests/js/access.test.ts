import { describe, expect, it } from 'vitest';
import { canVisit, isGuardedRoute, type AccessSubject, type GuardedRoute } from '@/router/access';

// Matriks permission = database/seeders/RolePermissionSeeder.php. Identitas
// (company_id/terminal_id/role) = akun demo docs/DUMMY-DATA.md.
const ALL = [
    'user.manage', 'role.manage', 'terminal.manage', 'gate.manage', 'company.manage', 'slot.manage', 'slot.read',
    'appointment.read', 'appointment.read.self', 'appointment.write', 'appointment.override', 'fleet.manage',
    'gate.process', 'report.read', 'audit.read',
];

function persona(role: string, permissions: string[], company_id: number | null, terminal_id: number | null): AccessSubject {
    return {
        can: (perm) => permissions.includes(perm),
        hasRole: (r) => r === role,
        user: { company_id, terminal_id },
    };
}

const PERSONAS: Record<string, AccessSubject> = {
    admin: persona('admin', ALL, null, null),
    planner: persona('planner', ['slot.manage', 'slot.read', 'appointment.read', 'appointment.override', 'report.read', 'audit.read'], null, null),
    gate: persona('gate-officer', ['gate.process', 'appointment.read', 'slot.read'], null, 1),
    transporter: persona('transporter', ['appointment.write', 'appointment.read', 'fleet.manage', 'slot.read', 'report.read', 'audit.read'], 1, null),
    driver: persona('driver', ['appointment.read.self'], 1, null),
};

// Harapan = menu yang terlihat tiap persona pada sweep Playwright 2026-10-30.
const EXPECTED: Record<string, GuardedRoute[]> = {
    admin: ['slots', 'planner', 'gate-history', 'admin'],
    planner: ['slots', 'planner', 'gate-history'],
    gate: ['slots', 'gate'],
    transporter: ['slots', 'bookings', 'trucks', 'reports'],
    driver: ['today'],
};

const ROUTES: GuardedRoute[] = ['slots', 'bookings', 'trucks', 'reports', 'today', 'gate', 'planner', 'gate-history', 'admin'];

describe('canVisit', () => {
    for (const [name, subject] of Object.entries(PERSONAS)) {
        it(`allows exactly the expected routes for ${name}`, () => {
            expect(ROUTES.filter((route) => canVisit(route, subject))).toEqual(EXPECTED[name]);
        });
    }

    it('denies everything when no user is loaded', () => {
        const nobody: AccessSubject = { can: () => true, hasRole: () => true, user: null };

        // can()/hasRole() true tapi tanpa identitas: rute ber-company/terminal tetap tertutup.
        expect(canVisit('bookings', nobody)).toBe(false);
        expect(canVisit('gate', nobody)).toBe(false);
    });
});

describe('isGuardedRoute', () => {
    it('treats only the role-scoped pages as guarded', () => {
        expect(ROUTES.every(isGuardedRoute)).toBe(true);
        for (const open of ['dashboard', 'login', 'forbidden', 'not-found', undefined, null, Symbol('x')]) {
            expect(isGuardedRoute(open)).toBe(false);
        }
    });
});
