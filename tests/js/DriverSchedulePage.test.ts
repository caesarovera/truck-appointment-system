import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';
import type { Appointment, SlotWindow } from '@/types/api';

const state = {
    appointments: ref<Appointment[]>([]),
    isLoading: ref(false),
    isError: ref(false),
};

vi.mock('@/composables/useTodaySchedule', () => ({ useTodaySchedule: () => state }));

import DriverSchedulePage from '@/pages/DriverSchedulePage.vue';

function appointment(id: number, startTime: string, gateName: string): Appointment {
    const slotWindow: SlotWindow = {
        id,
        gate_id: 1,
        date: '2026-06-28',
        start_time: startTime,
        end_time: '12:00:00',
        capacity: 10,
        booked_count: 1,
        remaining: 9,
        status: 'OPEN',
        gate: { id: 1, terminal_id: 1, code: 'GATE-A', name: gateName },
    };

    return {
        id,
        booking_code: `TAS-${id}`,
        status: 'CONFIRMED',
        move_type: 'DELIVERY',
        version: 1,
        company_id: 1,
        slot_window: slotWindow,
        truck: null,
        driver: null,
        containers: [],
        created_at: null,
    };
}

const mountPage = () => mount(DriverSchedulePage, { global: { stubs: { RouterLink: true } } });

beforeEach(() => {
    state.appointments.value = [];
    state.isLoading.value = false;
    state.isError.value = false;
});

describe('DriverSchedulePage', () => {
    it('shows an empty state when there is nothing scheduled', () => {
        expect(mountPage().text()).toContain('Tidak ada jadwal');
    });

    it('lists appointments sorted by start time with the gate name', () => {
        state.appointments.value = [appointment(2, '10:00:00', 'Gate B'), appointment(1, '08:00:00', 'Gate A')];

        const wrapper = mountPage();
        const rows = wrapper.findAll('[data-testid="schedule-row"]');

        expect(rows).toHaveLength(2);
        // Diurutkan: 08:00 (Gate A) sebelum 10:00 (Gate B).
        expect(rows[0].text()).toContain('08:00');
        expect(rows[0].text()).toContain('Gate A');
        expect(rows[1].text()).toContain('10:00');
    });

    it('shows an error alert when the query fails', () => {
        state.isError.value = true;

        expect(mountPage().find('[role="alert"]').exists()).toBe(true);
    });
});
