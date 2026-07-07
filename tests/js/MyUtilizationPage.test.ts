import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { Gate, SlotUtilization, UtilizationSummary } from '@/types/api';

const gatesState = { gates: ref<Gate[]>([]), isLoading: ref(false), isError: ref(false) };
const util = {
    windows: ref<SlotUtilization[]>([]),
    summary: ref<UtilizationSummary | null>(null),
    isLoading: ref(false),
    isError: ref(false),
    enabled: ref(true),
};

vi.mock('@/composables/useGates', () => ({ useGates: () => gatesState }));
vi.mock('@/composables/useMyUtilization', () => ({ useMyUtilization: () => util }));

import MyUtilizationPage from '@/pages/MyUtilizationPage.vue';

function window(overrides: Partial<SlotUtilization>): SlotUtilization {
    return {
        id: 1,
        start_time: '08:00:00',
        end_time: '09:00:00',
        status: 'OPEN',
        capacity: 10,
        booked_count: 3,
        remaining: 7,
        completed: 1,
        no_show: 0,
        cancelled: 0,
        active: 2,
        ...overrides,
    };
}

const mountPage = () => mount(MyUtilizationPage, { global: { stubs: { RouterLink: true } } });

beforeEach(() => {
    gatesState.gates.value = [{ id: 1, terminal_id: 1, code: 'GATE-A', name: 'Gate A' }];
    util.windows.value = [];
    util.summary.value = null;
    util.enabled.value = true;
    util.isLoading.value = false;
    util.isError.value = false;
    vi.clearAllMocks();
});

describe('MyUtilizationPage', () => {
    it('prompts for a gate before anything is loaded', () => {
        util.enabled.value = false;

        expect(mountPage().text()).toContain('Pilih gate');
    });

    it('shows the loading state', () => {
        util.isLoading.value = true;

        expect(mountPage().text()).toContain('Memuat laporan');
    });

    it('shows the error state', () => {
        util.isError.value = true;

        expect(mountPage().find('[role="alert"]').text()).toContain('Gagal memuat laporan');
    });

    it('shows the empty state when the gate has no windows', () => {
        expect(mountPage().text()).toContain('Tidak ada window');
    });

    it('lists per-window own counts plus the company summary', () => {
        util.windows.value = [
            window({ id: 1, completed: 2, no_show: 1 }),
            window({ id: 2, start_time: '09:00:00', end_time: '10:00:00', active: 1 }),
        ];
        util.summary.value = { capacity: 20, booked: 6, completed: 3, no_show: 1, cancelled: 0, active: 3 };

        const wrapper = mountPage();

        expect(wrapper.findAll('[data-testid="window-row"]')).toHaveLength(2);
        expect(wrapper.find('[data-testid="window-row"]').text()).toContain('selesai 2');
        expect(wrapper.find('[data-testid="summary"]').text()).toContain('No-show');
        // Konteks gate global tetap terlihat, terpisah dari hitungan milik sendiri.
        expect(wrapper.find('[data-testid="window-row"]').text()).toContain('terisi 3/10');
    });
});
