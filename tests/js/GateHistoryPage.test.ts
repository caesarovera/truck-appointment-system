import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { Appointment, Gate } from '@/types/api';

const gatesState = { gates: ref<Gate[]>([]), isLoading: ref(false), isError: ref(false) };
const history = {
    appointments: ref<Appointment[]>([]),
    isLoading: ref(false),
    isError: ref(false),
    enabled: ref(true),
};

vi.mock('@/composables/useGates', () => ({ useGates: () => gatesState }));
vi.mock('@/composables/useGateHistory', () => ({ useGateHistory: () => history }));

import GateHistoryPage from '@/pages/GateHistoryPage.vue';

function appointment(overrides: Partial<Appointment>): Appointment {
    return {
        id: 1,
        booking_code: 'TAS-AAA',
        status: 'COMPLETED',
        move_type: 'DELIVERY',
        version: 1,
        company_id: 1,
        company: { id: 1, code: 'MAJU', name: 'PT Maju Logistik' },
        slot_window: null,
        truck: { id: 1, plate_no: 'B 9011 XX', status: 'ACTIVE' },
        driver: { id: 2, name: 'Budi' },
        containers: [],
        gate_in_at: '2026-08-13T09:00:00.000000Z',
        gate_out_at: '2026-08-13T09:45:00.000000Z',
        dwell_minutes: 45,
        created_at: null,
        ...overrides,
    };
}

const mountPage = () => mount(GateHistoryPage, { global: { stubs: { RouterLink: true } } });

beforeEach(() => {
    gatesState.gates.value = [{ id: 1, terminal_id: 1, code: 'GATE-A', name: 'Gate A' }];
    history.appointments.value = [];
    history.isLoading.value = false;
    history.isError.value = false;
    history.enabled.value = true;
    vi.clearAllMocks();
});

describe('GateHistoryPage', () => {
    it('renders one card per appointment with gate times and dwell minutes', () => {
        history.appointments.value = [appointment({ id: 1 })];

        const wrapper = mountPage();

        expect(wrapper.findAll('[data-testid="history-row"]')).toHaveLength(1);
        expect(wrapper.text()).toContain('B 9011 XX');
        expect(wrapper.text()).toContain('PT Maju Logistik');
        expect(wrapper.text()).toContain('09:00');
        expect(wrapper.text()).toContain('09:45');
        expect(wrapper.text()).toContain('45 menit');
    });

    it('shows a dash for gate out when the truck has not gated out yet', () => {
        history.appointments.value = [appointment({ id: 1, status: 'IN_PROGRESS', gate_out_at: null, dwell_minutes: null })];

        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Gate out —');
    });

    it('sorts by gate-in time (client-side — repo intentionally does not sort by relation column)', () => {
        history.appointments.value = [
            appointment({ id: 1, booking_code: 'TAS-LATE', gate_in_at: '2026-08-13T11:00:00.000000Z' }),
            appointment({ id: 2, booking_code: 'TAS-EARLY', gate_in_at: '2026-08-13T08:00:00.000000Z' }),
        ];

        const rows = mountPage().findAll('[data-testid="history-row"]');

        expect(rows[0]?.text()).toContain('TAS-EARLY');
        expect(rows[1]?.text()).toContain('TAS-LATE');
    });

    it('prompts for a gate while the query is disabled', () => {
        history.enabled.value = false;

        expect(mountPage().text()).toContain('Pilih gate untuk melihat riwayat');
    });

    it('shows an empty state when nobody has gated in yet', () => {
        expect(mountPage().text()).toContain('Belum ada truk yang gate-in');
    });

    it('shows an error alert when the query fails', () => {
        history.isError.value = true;

        expect(mountPage().find('[role="alert"]').text()).toContain('Gagal memuat riwayat gate');
    });
});
