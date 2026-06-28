import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { Gate, SlotWindow } from '@/types/api';

// Komponen diuji terisolasi: composable (TanStack Query) di-mock jadi ref terkontrol,
// persis pola LoginPage (mock store). Tak perlu QueryClient/jaringan.
const state = {
    windows: ref<SlotWindow[]>([]),
    isLoading: ref(false),
    isFetching: ref(false),
    isError: ref(false),
    enabled: ref(true),
};

const gatesState = {
    gates: ref<Gate[]>([]),
    isLoading: ref(false),
    isError: ref(false),
};

vi.mock('@/composables/useSlotAvailability', () => ({
    useSlotAvailability: () => state,
}));

vi.mock('@/composables/useGates', () => ({
    useGates: () => gatesState,
}));

let canPermissions: string[] = [];
vi.mock('@/stores/auth', () => ({
    useAuthStore: () => ({ can: (p: string) => canPermissions.includes(p) }),
}));

import SlotAvailabilityPage from '@/pages/SlotAvailabilityPage.vue';

const mountPage = () =>
    mount(SlotAvailabilityPage, { global: { stubs: { RouterLink: true, BookingForm: true } } });

function window(overrides: Partial<SlotWindow>): SlotWindow {
    return {
        id: 1,
        gate_id: 1,
        date: '2026-06-28',
        start_time: '08:00:00',
        end_time: '09:00:00',
        capacity: 10,
        booked_count: 3,
        remaining: 7,
        status: 'OPEN',
        ...overrides,
    };
}

beforeEach(() => {
    state.windows.value = [];
    state.isLoading.value = false;
    state.isFetching.value = false;
    state.isError.value = false;
    state.enabled.value = true;
    gatesState.gates.value = [];
    gatesState.isLoading.value = false;
    canPermissions = [];
});

describe('SlotAvailabilityPage', () => {
    it('renders an option per gate in the selector', () => {
        gatesState.gates.value = [
            { id: 1, terminal_id: 1, code: 'GATE-A', name: 'Gate A' },
            { id: 2, terminal_id: 1, code: 'GATE-B', name: 'Gate B' },
        ];

        const wrapper = mountPage();
        const options = wrapper.findAll('select option');

        // 1 placeholder ("Pilih gate") + 2 gate.
        expect(options).toHaveLength(3);
        expect(wrapper.text()).toContain('Gate A');
        expect(wrapper.text()).toContain('Gate B');
    });

    it('prompts for a gate while the query is disabled', () => {
        state.enabled.value = false;

        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Pilih gate untuk melihat slot');
        expect(wrapper.find('[data-testid="slot-list"]').exists()).toBe(false);
    });

    it('renders one card per window with remaining and an availability badge', () => {
        state.windows.value = [
            window({ id: 1, start_time: '08:00:00', end_time: '09:00:00', remaining: 7 }),
            window({ id: 2, start_time: '09:00:00', end_time: '10:00:00', capacity: 5, booked_count: 5, remaining: 0 }),
        ];

        const wrapper = mountPage();

        expect(wrapper.findAll('[data-testid="slot-card"]')).toHaveLength(2);
        expect(wrapper.text()).toContain('08:00–09:00');
        expect(wrapper.text()).toContain('Tersedia'); // window 1: remaining > 0
        expect(wrapper.text()).toContain('Penuh'); // window 2: remaining 0
    });

    it('shows an empty state when there are no open windows', () => {
        state.windows.value = [];

        expect(mountPage().text()).toContain('Tidak ada slot terbuka');
    });

    it('shows an error alert when the query fails', () => {
        state.isError.value = true;

        const wrapper = mountPage();

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    });

    it('shows a Booking button on available windows when the user can book', () => {
        canPermissions = ['appointment.write'];
        state.windows.value = [
            window({ id: 1, remaining: 7 }),
            window({ id: 2, remaining: 0 }), // penuh → tak ada tombol
        ];

        const buttons = mountPage().findAll('[data-testid="book-button"]');

        expect(buttons).toHaveLength(1);
    });

    it('hides the Booking button when the user lacks appointment.write', () => {
        canPermissions = []; // mis. planner/driver
        state.windows.value = [window({ id: 1, remaining: 7 })];

        expect(mountPage().find('[data-testid="book-button"]').exists()).toBe(false);
    });
});
